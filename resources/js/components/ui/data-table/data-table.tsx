"use client"

import { Card, CardContent } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"
import { getQuery } from "@/lib/utils"
import type React from "react"
import { useState } from "react"
import { DataTablePagination } from "./data-pagination"

export type LaravelPaginationLink = {
    url: string | null
    label: string
    active: boolean
}

export type LaravelPagination<T> = {
    current_page: number
    data: T[]
    first_page_url: string
    from: number
    last_page: number
    last_page_url: string
    links: LaravelPaginationLink[]
    next_page_url: string | null
    path: string
    per_page: number
    prev_page_url: string | null
    to: number
    total: number
}

export type Column<T> = {
    key: string
    label: string
    render?: (item: T) => React.ReactNode
}

interface DataTableProps<T> {
    data: LaravelPagination<T> | null | undefined
    columns: Column<T>[]
    onPageChange: (page: number) => void
    onSearch?: (query: string) => void
    searchPlaceholder?: string
    isLoading?: boolean
}

// Default empty pagination object
const emptyPagination: LaravelPagination<any> = {
    current_page: 1,
    data: [],
    first_page_url: "",
    from: 0,
    last_page: 1,
    last_page_url: "",
    links: [],
    next_page_url: null,
    path: "",
    per_page: 10,
    prev_page_url: null,
    to: 0,
    total: 0,
}

export function DataTable<T>({
    data,
    columns,
    onPageChange,
    onSearch,
    searchPlaceholder = "Search...",
    isLoading = false,
}: DataTableProps<T>) {

    const search = getQuery('search');
    const [searchQuery, setSearchQuery] = useState(search || undefined)

    const paginationData = data || emptyPagination

    const handleSearch = (e: React.ChangeEvent<HTMLInputElement>) => {
        const query = e.target.value
        setSearchQuery(query)

        const handler = setTimeout(() => {
            onSearch?.(query)
        }, 300)

        return () => clearTimeout(handler)
    }

    const getValue = (item: any, key: string) => {
        return key.split(".").reduce((o, i) => o?.[i], item)
    }

    return (
        <Card>
            <CardContent className="p-6">
                <div className="space-y-4">
                    {onSearch && (
                        <div className="flex items-center">
                            <Input placeholder={searchPlaceholder} value={searchQuery} onChange={handleSearch} className="max-w-sm" />
                        </div>
                    )}

                    <div className="rounded-md border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    {columns.map((column) => (
                                        <TableHead key={column.key}>{column.label}</TableHead>
                                    ))}
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {isLoading ? (
                                    <TableRow>
                                        <TableCell colSpan={columns.length} className="h-24 text-center">
                                            Loading...
                                        </TableCell>
                                    </TableRow>
                                ) : !paginationData.data || paginationData.data.length === 0 ? (
                                    <TableRow>
                                        <TableCell colSpan={columns.length} className="h-24 text-center">
                                            No results found.
                                        </TableCell>
                                    </TableRow>
                                ) : (
                                    paginationData.data.map((item: any, index) => (
                                        <TableRow key={item.id || index}>
                                            {columns.map((column) => (
                                                <TableCell key={`${item.id || index}-${column.key}`}>
                                                    {column.render ? column.render(item) : getValue(item, column.key)}
                                                </TableCell>
                                            ))}
                                        </TableRow>
                                    ))
                                )}
                            </TableBody>
                        </Table>
                    </div>

                    <DataTablePagination
                        currentPage={paginationData.current_page}
                        lastPage={paginationData.last_page}
                        from={paginationData.from}
                        to={paginationData.to}
                        total={paginationData.total}
                        onPageChange={onPageChange}
                        links={paginationData.links}
                    />
                </div>
            </CardContent>
        </Card>
    )
}
