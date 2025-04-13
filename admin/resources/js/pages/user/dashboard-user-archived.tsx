import { Button } from "@/components/ui/button";
import { DataTable, LaravelPagination } from "@/components/ui/data-table/data-table";
import AppLayout from "@/layouts/app-layout";
import { usePermission } from "@/lib/auth";
import { getQuery } from "@/lib/utils";
import { BreadcrumbItem } from "@/types";
import { Head, router } from "@inertiajs/react";
import { ArrowLeft } from "lucide-react";
import { useState } from "react";
import { userColumns, UserDataTable } from "./datatable/user-columns";

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

export default function DashboardDatabaseArchived({
    users
}: {
    users: LaravelPagination<UserDataTable>
}) {
    const { hasRole } = usePermission();
    const search = getQuery('search');
    const [data, setData] = useState<LaravelPagination<UserDataTable>>(users)
    const [isLoading, setIsLoading] = useState(false)
    const [searchQuery, setSearchQuery] = useState(search)

    const fetchData = async (page: number, search?: string) => {
        setIsLoading(true)

        if (search) {
            router.visit(`${route('dashboard.users')}?search=${search}&page=${page}`)
        } else {
            router.visit(route('dashboard.users'))
        }

        setData({
            ...users,
            current_page: page,
        })

        setIsLoading(false)
    }

    const handlePageChange = (page: number) => {
        fetchData(page, searchQuery)
    }

    const handleSearch = (query: string) => {
        setSearchQuery(query)
        fetchData(1, query)
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Users" />
            <div className="container mx-auto py-8">
                <div className="flex items-center justify-between">
                    <div className="mb-6">
                        <h1 className="text-2xl font-bold">Users</h1>
                        <p className="text-muted-foreground text-sm">List all your users</p>
                    </div>
                    {hasRole('Super Admin') && (
                        <Button variant={'default'} onClick={() => router.visit(route('dashboard.users'))}>
                            <ArrowLeft className="h-4 w-4" />
                            Users
                        </Button>
                    )}
                </div>
                <DataTable
                    data={data}
                    columns={userColumns}
                    onPageChange={handlePageChange}
                    onSearch={handleSearch}
                    searchPlaceholder="Search users..."
                    isLoading={isLoading}
                />
            </div>
        </AppLayout>
    )
}
