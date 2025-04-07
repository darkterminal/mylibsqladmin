"use client"
import { Button } from "@/components/ui/button"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { ChevronLeft, ChevronRight, ChevronsLeft, ChevronsRight } from "lucide-react"
import type { LaravelPaginationLink } from "./data-table"

interface DataTablePaginationProps {
    currentPage: number
    lastPage: number
    from: number
    to: number
    total: number
    onPageChange: (page: number) => void
    links: LaravelPaginationLink[]
}

export function DataTablePagination({
    currentPage,
    lastPage,
    from,
    to,
    total,
    onPageChange,
    links,
}: DataTablePaginationProps) {
    // Handle empty links array
    const safeLinks = links || []

    // Extract numeric pages from links (excluding Previous/Next)
    const numericLinks = safeLinks.filter(
        (link) =>
            !link.label.includes("Previous") &&
            !link.label.includes("Next") &&
            !link.label.includes("&laquo;") &&
            !link.label.includes("&raquo;"),
    )

    // Check if we have previous and next links
    const hasPrevious =
        safeLinks.some((link) => link.label.includes("Previous") || link.label.includes("&laquo;")) && currentPage > 1

    const hasNext =
        safeLinks.some((link) => link.label.includes("Next") || link.label.includes("&raquo;")) && currentPage < lastPage

    return (
        <div className="flex flex-col sm:flex-row items-center justify-between gap-4 px-2">
            <div className="text-sm text-muted-foreground">
                {total > 0 ? (
                    <>
                        Showing <span className="font-medium">{from}</span> to <span className="font-medium">{to}</span> of{" "}
                        <span className="font-medium">{total}</span> results
                    </>
                ) : (
                    "No results"
                )}
            </div>

            {total > 0 && (
                <div className="flex items-center space-x-2">
                    <Button
                        variant="outline"
                        size="icon"
                        className="h-8 w-8"
                        onClick={() => onPageChange(1)}
                        disabled={currentPage === 1}
                    >
                        <span className="sr-only">Go to first page</span>
                        <ChevronsLeft className="h-4 w-4" />
                    </Button>

                    <Button
                        variant="outline"
                        size="icon"
                        className="h-8 w-8"
                        onClick={() => onPageChange(currentPage - 1)}
                        disabled={!hasPrevious}
                    >
                        <span className="sr-only">Go to previous page</span>
                        <ChevronLeft className="h-4 w-4" />
                    </Button>

                    {/* Show numeric page buttons on larger screens */}
                    <div className="hidden sm:flex items-center">
                        {numericLinks.map((link, i) => (
                            <Button
                                key={i}
                                variant={link.active ? "default" : "outline"}
                                size="icon"
                                className="h-8 w-8"
                                onClick={() => {
                                    if (link.url) {
                                        const pageNumber = Number.parseInt(link.label)
                                        onPageChange(pageNumber)
                                    }
                                }}
                                disabled={!link.url}
                            >
                                <span dangerouslySetInnerHTML={{ __html: link.label }} />
                            </Button>
                        ))}
                    </div>

                    {/* Show page selector on small screens */}
                    <div className="sm:hidden">
                        <Select value={currentPage.toString()} onValueChange={(value) => onPageChange(Number.parseInt(value))}>
                            <SelectTrigger className="h-8 w-16">
                                <SelectValue placeholder={currentPage} />
                            </SelectTrigger>
                            <SelectContent>
                                {Array.from({ length: lastPage }, (_, i) => i + 1).map((page) => (
                                    <SelectItem key={page} value={page.toString()}>
                                        {page}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>

                    <Button
                        variant="outline"
                        size="icon"
                        className="h-8 w-8"
                        onClick={() => onPageChange(currentPage + 1)}
                        disabled={!hasNext}
                    >
                        <span className="sr-only">Go to next page</span>
                        <ChevronRight className="h-4 w-4" />
                    </Button>

                    <Button
                        variant="outline"
                        size="icon"
                        className="h-8 w-8"
                        onClick={() => onPageChange(lastPage)}
                        disabled={currentPage === lastPage}
                    >
                        <span className="sr-only">Go to last page</span>
                        <ChevronsRight className="h-4 w-4" />
                    </Button>
                </div>
            )}
        </div>
    )
}
