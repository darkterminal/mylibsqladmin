import { Button } from "@/components/ui/button";
import { DataTable, LaravelPagination } from "@/components/ui/data-table/data-table";
import AppLayout from "@/layouts/app-layout";
import { usePermission } from "@/lib/auth";
import { BreadcrumbItem } from "@/types";
import { Head, router } from "@inertiajs/react";
import { ArrowLeft } from "lucide-react";
import { userColumns, UserDataTable } from "./datatable/user-columns";

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Users',
        href: '/dashboard/users',
    },
    {
        title: 'Archived',
        href: '/users/archive',
    }
];

export default function DashboardUserArchived({
    users
}: {
    users: LaravelPagination<UserDataTable>
}) {
    const { hasRole } = usePermission();

    const visit = (page: number, search?: string) => {
        const params = new URLSearchParams();
        if (search && search.trim() !== '') params.set('search', search.trim());
        if (page > 1) params.set('page', String(page));
        router.visit(`${route('user.archive')}?${params.toString()}`);
    }

    const handlePageChange = (page: number) => {
        visit(page, new URLSearchParams(window.location.search).get('search') ?? undefined);
    }

    const handleSearch = (query: string) => {
        visit(1, query);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Archived Users" />
            <div className="container mx-auto p-6 space-y-6">
                <div className="flex items-center justify-between">
                    <div className="mb-6">
                        <h1 className="text-2xl font-bold">Archived Users</h1>
                        <p className="text-muted-foreground text-sm">Soft-deleted accounts. Restore them or remove permanently.</p>
                    </div>
                    {hasRole('Super Admin') && (
                        <Button variant={'outline'} onClick={() => router.visit(route('dashboard.users'))}>
                            <ArrowLeft className="h-4 w-4" />
                            Back to Users
                        </Button>
                    )}
                </div>
                <DataTable
                    data={users}
                    columns={userColumns}
                    onPageChange={handlePageChange}
                    onSearch={handleSearch}
                    searchPlaceholder="Search archived users by name, username or email..."
                />
            </div>
        </AppLayout>
    )
}
