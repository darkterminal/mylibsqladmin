import { Column, DataTable, LaravelPagination } from "@/components/ui/data-table/data-table"
import AppLayout from "@/layouts/app-layout"
import { getQuery } from "@/lib/utils"
import { BreadcrumbItem } from "@/types"
import { Head, router } from "@inertiajs/react"
import { useState } from "react"

/**
 * Shared page shell for the database lists. The parent page injects the
 * rendered columns, its visit target and the header action button; this
 * component owns the search/page state and the single data flow.
 */
export function DatabasesPage<T>({
    breadcrumbs,
    headTitle,
    title,
    subtitle,
    headerAction,
    listRouteName,
    initialData,
    columns,
    partialOnly,
}: {
    breadcrumbs: BreadcrumbItem[]
    headTitle: string
    title: string
    subtitle: string
    headerAction: React.ReactNode
    /** Route name passed to the Inertia visit when paginating/searching, e.g. 'dashboard.databases'. */
    listRouteName: string
    initialData: LaravelPagination<T>
    columns: Column<T>[]
    /** Inertia partial-reload props, e.g. ['listOfDatabases'] — unset refetches the whole page. */
    partialOnly?: string[]
}) {
    const searchQueryFromUrl = getQuery('search') ?? ''
    const [isLoading, setIsLoading] = useState(false)
    const [searchQuery, setSearchQuery] = useState(searchQueryFromUrl)

    const visit = (url: string) => {
        setIsLoading(true)
        router.visit(url, {
            preserveState: true,
            replace: true,
            preserveScroll: true,
            only: partialOnly,
            onFinish: () => setIsLoading(false),
            // a visit superseded by a newer one (rapid typing into the
            // search box) is cancelled without firing onFinish — without
            // this the table would stay on the "Loading..." row forever
            onCancel: () => setIsLoading(false),
            onError: () => setIsLoading(false),
        })
    }

    const handlePageChange = (page: number) => {
        const searchPart = searchQuery ? `&search=${encodeURIComponent(searchQuery)}` : ''
        visit(`${route(listRouteName)}?page=${page}${searchPart}`)
    }

    const handleSearch = (query: string) => {
        setSearchQuery(query)
        const searchPart = query ? `&search=${encodeURIComponent(query)}` : ''
        visit(`${route(listRouteName)}?page=1${searchPart}`)
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={headTitle} />
            <div className="container mx-auto py-8 px-4">
                <div className="flex items-center justify-between">
                    <div className="mb-6">
                        <h1 className="text-2xl font-bold">{title}</h1>
                        <p className="text-muted-foreground text-sm">{subtitle}</p>
                    </div>
                    {headerAction}
                </div>
                <DataTable
                    data={initialData}
                    columns={columns}
                    onPageChange={handlePageChange}
                    onSearch={handleSearch}
                    searchPlaceholder="Search databases..."
                    isLoading={isLoading}
                />
            </div>
        </AppLayout>
    )
}
