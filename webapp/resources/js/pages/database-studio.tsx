import { AppDatabaseSidebar } from '@/components/app-database-sidebar';
import { LibsqlStudio } from '@/components/libsql-studio';
import { useCustomEvent } from '@/hooks/use-custom-event';
import AppLayout from '@/layouts/app-layout';
import { apiFetch } from '@/lib/api';
import { getQuery } from '@/lib/utils';
import {
    Configs,
    SharedData,
    type BreadcrumbItem,
    type DatabaseStatsChangeProps,
    type LibSQLDatabases,
} from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowRight, Database, Info } from "lucide-react";
import { Button } from "@/components/ui/button";
import { useEffect, useRef, useState } from "react";
import { getCurrentTeamId } from "@/lib/current-team"

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Database Studio',
        href: '/database-studio',
    },
];

export default function DatabaseStudio() {
    const { props } = usePage<SharedData>();
    const scheme = window.location.protocol.replace(':', '');
    const { sqldHost, sqldPort } = props.configs as Configs;

    const userDatabases = props.databases as LibSQLDatabases[];

    const [clientUrl, setClientUrl] = useState<string | null>("http://localhost:8080");
    const [authToken, setAuthToken] = useState<string | undefined>(undefined);
    const [tokenState, setTokenState] = useState<'loading' | 'ready' | 'error'>('loading');
    const [tokenError, setTokenError] = useState<string | null>(null);

    const parent = getQuery('database');
    const [databaseName, setDatabaseName] = useState<string | null>(parent);

    const [dataSidebarState, setDataSidebarState] = useState<string>('');
    const targetRef = useRef<HTMLElement | null>(null);
    const observerRef = useRef<MutationObserver | null>(null);

    useEffect(() => {
        targetRef.current = document.querySelector('[data-state]');

        if (!targetRef.current) return;

        setDataSidebarState(targetRef.current.getAttribute('data-state') || '');

        observerRef.current = new MutationObserver((mutations) => {
            mutations.forEach((mutation) => {
                if (mutation.type === 'attributes' && mutation.attributeName === 'data-state') {
                    const newState = (mutation.target as HTMLElement).getAttribute('data-state') || '';
                    setDataSidebarState(newState);
                }
            });
        });

        observerRef.current.observe(targetRef.current, {
            attributes: true,
            attributeFilter: ['data-state']
        });

        return () => {
            observerRef.current?.disconnect();
        };
    }, []);

    useEffect(() => {
        if (parent) {
            setDatabaseName(parent);
            setClientUrl(`${scheme}://${parent}.${sqldHost}:${sqldPort}`);
        }
    }, [parent]);

    // Token for the selected database is created automatically for this
    // user on every visit — fresh plaintext each time, never persisted
    // anywhere on the server, and only this user ever sees it.
    useEffect(() => {
        if (!databaseName) return;

        let cancelled = false;
        setAuthToken(undefined);
        setTokenState('loading');
        setTokenError(null);

        apiFetch(route('database-studio.token'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': props.csrfToken,
            },
            body: JSON.stringify({ database: databaseName }),
        }).then(async (res) => {
            if (!res.ok) {
                let message = 'Studio token could not be created';
                try {
                    const json = await res.json();
                    if (json?.message) message = json.message;
                } catch { /* no-op */ }
                throw new Error(message);
            }
            const json = await res.json();
            if (!cancelled) {
                setAuthToken(json.full_access_token);
                setTokenState('ready');
            }
        }).catch((e: Error) => {
            if (!cancelled) {
                setTokenError(e?.message || 'Studio token could not be created');
                setTokenState('error');
            }
        });

        return () => { cancelled = true; };
    }, [databaseName]);

    useCustomEvent<DatabaseStatsChangeProps>('stats-changed', async ({ databaseName, statement, type }) => {
        const statsEndpoint = route('trigger.stats-changed', { databaseName, source: 'web' });
        const logEndpoint = route('activities.store');
        const currentTeamId = getCurrentTeamId() || 'null';

        if (statement !== undefined) {
            await apiFetch(logEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': props.csrfToken
                },
                body: JSON.stringify({
                    team_id: currentTeamId,
                    database_name: databaseName,
                    query: statement
                }),
            });
        }

        await fetch(statsEndpoint, {
            method: 'GET',
            headers: {
                'Content-Type': 'application/json',
            }
        });
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Database Studio" />
            <div className="flex h-full flex-1">
                {dataSidebarState === 'collapsed' && <AppDatabaseSidebar databaseName={databaseName} userDatabases={userDatabases} />}
                {databaseName ? (
                    tokenState === 'ready' && authToken ? (
                        <div className="flex flex-1 flex-col min-w-0">
                            <div className="flex items-center gap-2 border-b bg-muted/40 px-4 py-1.5 text-xs text-muted-foreground">
                                <Info className="h-3.5 w-3.5 shrink-0" />
                                <span>
                                    A full-access token for <span className="font-medium text-foreground">{databaseName}</span> is created automatically for your account — refreshed on every visit, visible only to you.
                                </span>
                            </div>
                            <LibsqlStudio databaseName={databaseName} clientUrl={clientUrl} authToken={authToken} />
                        </div>
                    ) : tokenState === 'error' ? (
                        <div className="flex flex-1 flex-col items-center justify-center gap-3 p-6 text-center">
                            <Database className="h-12 w-12 text-muted-foreground" />
                            <div className="space-y-1">
                                <h2 className="text-xl font-semibold">Studio could not be opened</h2>
                                <p className="text-sm text-muted-foreground">{tokenError ?? 'Studio token could not be created'}</p>
                            </div>
                            <Button asChild variant="default">
                                <Link href={route('dashboard.databases')}>
                                    Go to Databases
                                    <ArrowRight className="ml-2 h-4 w-4" />
                                </Link>
                            </Button>
                        </div>
                    ) : (
                        <div className="flex flex-1 items-center justify-center p-6 text-sm text-muted-foreground">
                            Preparing Studio…
                        </div>
                    )
                ) : (
                    <div className="flex flex-1 flex-col items-center justify-center gap-4 p-6 text-center">
                        <Database className="h-12 w-12 text-muted-foreground" />
                        <div className="space-y-1">
                            <h2 className="text-xl font-semibold">No database selected</h2>
                            <p className="text-sm text-muted-foreground">
                                Pick a database from the Databases page to open it in Studio.
                            </p>
                        </div>
                        <Button asChild variant="default">
                            <Link href={route('dashboard.databases')}>
                                Go to Databases
                                <ArrowRight className="ml-2 h-4 w-4" />
                            </Link>
                        </Button>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
