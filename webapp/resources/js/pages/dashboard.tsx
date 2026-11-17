import StatisticsDashboard from '@/components/analytics/statistics-dashboard';
import DashboardStatisticSkeleton from '@/components/skeletons/DashboardStatisticSkeleton';
import AppLayout from '@/layouts/app-layout';
import { type BreadcrumbItem, type DashboardMetrics, type MostUsedDatabaseProps, type RecentActivityItem } from '@/types';
import { Deferred, Head } from '@inertiajs/react';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
];

export default function Dashboard({
    databaseMetrics,
    mostUsedDatabases,
    recentActivity
}: {
    databaseMetrics: DashboardMetrics,
    mostUsedDatabases: MostUsedDatabaseProps[],
    recentActivity: RecentActivityItem[]
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 rounded-xl px-4 py-2">
                <Deferred data={['databaseMetrics', 'mostUsedDatabases', 'recentActivity']} fallback={<DashboardStatisticSkeleton />}>
                    <StatisticsDashboard
                        databaseMetrics={databaseMetrics}
                        mostUsedDatabases={mostUsedDatabases ?? []}
                        recentActivity={recentActivity ?? []}
                    />
                </Deferred>
            </div>
        </AppLayout>
    );
}
