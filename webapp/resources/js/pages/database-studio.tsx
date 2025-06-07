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
import { Head, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from "react";

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
    const [authToken, _] = useState<string | undefined>("");

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

    useCustomEvent<DatabaseStatsChangeProps>('stats-changed', async ({ databaseName, statement, type }) => {
        const statsEndpoint = route('trigger.stats-changed', { databaseName, source: 'web' });
        const logEndpoint = route('activities.store');
        const currentTeamId = localStorage.getItem('currentTeamId') || 'null';

        if (statement !== undefined) {
            await apiFetch(logEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    team_id: currentTeamId,
                    database_name: databaseName,
                    query: statement
                })
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
                <LibsqlStudio databaseName={databaseName} clientUrl={clientUrl} authToken={authToken} />
            </div>
        </AppLayout>
    );
}
