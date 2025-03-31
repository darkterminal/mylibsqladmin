import StatisticsDashboard from '@/components/analytics/statistics-dashboard';
import { AppDatabaseSidebar } from '@/components/app-database-sidebar';
import { LibsqlStudio } from '@/components/libsql-studio';
import DashboardStatisticSkeleton from '@/components/skeletons/DashboardStatisticSkeleton';
import { useCustomEvent } from '@/hooks/use-custom-event';
import AppLayout from '@/layouts/app-layout';
import { apiFetch } from '@/lib/api';
import { getQuery } from '@/lib/utils';
import {
    SharedData,
    type BreadcrumbItem,
    type DatabaseStatsChangeProps,
    type LibSQLDatabases,
    type MostUsedDatabaseProps,
    type QueryMetrics
} from '@/types';
import { Deferred, Head, usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from "react";

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

export default function Dashboard({ databaseMetrics, mostUsedDatabases }: { databaseMetrics: QueryMetrics[], mostUsedDatabases: MostUsedDatabaseProps[] }) {

    const { props } = usePage<SharedData>();
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
            setClientUrl(`http://${parent}.localhost:8080`);
        }
    }, [parent]);

    useCustomEvent<DatabaseStatsChangeProps>('stats-changed', async ({ databaseName, statement, type }) => {
        const statsEndpoint = route('trigger.stats-changed', { databaseName });
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
            <Head title="Dashboard" />
            {clientUrl && databaseName ? (
                <div className="flex h-full flex-1">
                    {dataSidebarState === 'collapsed' && <AppDatabaseSidebar databaseName={databaseName} userDatabases={userDatabases} />}
                    <LibsqlStudio databaseName={databaseName} clientUrl={clientUrl} authToken={authToken} />
                </div>
            ) : (
                <div className="flex h-full flex-1 flex-col gap-4 rounded-xl px-4 py-2">
                    <Deferred data={['databaseMetrics', 'mostUsedDatabases']} fallback={<DashboardStatisticSkeleton />}>
                        <StatisticsDashboard
                            databasesData={databaseMetrics ?? []}
                            mostUsedDatabases={mostUsedDatabases ?? []}
                        />
                    </Deferred>
                </div>
            )}
        </AppLayout>
    );
}
