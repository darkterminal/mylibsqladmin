import { Button } from "@/components/ui/button";
import { DataTable, LaravelPagination } from "@/components/ui/data-table/data-table";
import AppLayout from "@/layouts/app-layout";
import { usePermission } from "@/lib/auth";
import { BreadcrumbItem } from "@/types";
import { Head, router } from "@inertiajs/react";
import { Archive, PlusCircle } from "lucide-react";
import { useDisplayTimezone } from "@/lib/timezone";
import { createUserColumns, UserDataTable } from "./datatable/user-columns";

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Users',
        href: '/dashboard/users',
    }
];

export default function DashboardUser({
    users
}: {
    users: LaravelPagination<UserDataTable>
}) {
    const { hasRole } = usePermission();
    const tz = useDisplayTimezone();
    const userColumns = createUserColumns(tz);

    const visit = (page: number, search?: string) => {
        const params = new URLSearchParams();
        if (search && search.trim() !== '') params.set('search', search.trim());
        if (page > 1) params.set('page', String(page));
        router.visit(`${route('dashboard.users')}?${params.toString()}`);
    }

    const handlePageChange = (page: number) => {
        visit(page, new URLSearchParams(window.location.search).get('search') ?? undefined);
    }

    const handleSearch = (query: string) => {
        visit(1, query);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Users" />
            <div className="container mx-auto p-6 space-y-6">
                <div className="flex items-center justify-between">
                    <div className="mb-6">
                        <h1 className="text-2xl font-bold">Users</h1>
                        <p className="text-muted-foreground text-sm">List all your users</p>
                    </div>
                    {hasRole('Super Admin') && (
                        <div className="flex items-center gap-2">
                            <Button variant="default" onClick={() => router.get(route('user.create'))}>
                                <PlusCircle className="h-4 w-4" />
                                Add User
                            </Button>
                            <Button variant="outline" onClick={() => router.get(route('user.archive'))}>
                                <Archive className="h-4 w-4" />
                                Users Archived
                            </Button>
                        </div>
                    )}
                </div>
                <DataTable
                    data={users}
                    columns={userColumns}
                    onPageChange={handlePageChange}
                    onSearch={handleSearch}
                    searchPlaceholder="Search users by name, username or email..."
                />
            </div>
        </AppLayout>
    )
}
