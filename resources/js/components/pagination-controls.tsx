import {
    Pagination,
    PaginationContent,
    PaginationItem,
    PaginationLink,
    PaginationNext,
    PaginationPrevious,
} from "@/components/ui/pagination";
import { PaginatedResults } from "@/types";
import { Link } from "@inertiajs/react";
import { ChevronLeftIcon, ChevronRightIcon } from "lucide-react";

export function PaginationControls({ pagination }: { pagination: PaginatedResults<unknown> }) {
    const showPagination = pagination.last_page > 1;
    const previousUrl = pagination.current_page > 1 ? pagination.links[pagination.current_page - 1]?.url : null;
    const nextUrl = pagination.current_page < pagination.last_page ? pagination.links[pagination.current_page + 1]?.url : null;

    return (
        <Pagination className="py-4">
            <PaginationContent className="flex flex-row justify-between w-full">
                {/* Pagination Info */}
                <div className="flex-1 text-sm text-muted-foreground mr-4">
                    Page {pagination.current_page} of {pagination.last_page} ({pagination.total} items)
                </div>

                {showPagination && (
                    <div className="flex items-center gap-2">
                        {/* Previous Page */}
                        <PaginationItem>
                            {previousUrl ? (
                                <PaginationPrevious asChild>
                                    <Link href={previousUrl} preserveState>
                                        <ChevronLeftIcon />
                                        <span className="hidden sm:block">Previous</span>
                                    </Link>
                                </PaginationPrevious>
                            ) : (
                                <PaginationPrevious
                                    className="opacity-50 cursor-not-allowed"
                                    aria-disabled="true"
                                    onClick={(e) => e.preventDefault()}
                                />
                            )}
                        </PaginationItem>

                        {/* Page Numbers */}
                        {pagination.links.slice(1, -1).map((link, index) => (
                            <PaginationItem key={index}>
                                <PaginationLink
                                    asChild
                                    isActive={link.active}
                                >
                                    <Link
                                        href={link.url || "#"}
                                        preserveState
                                        className={
                                            link.active
                                                ? "bg-primary text-primary-foreground hover:bg-primary/90"
                                                : "hover:bg-accent hover:text-accent-foreground"
                                        }
                                    >
                                        {link.label}
                                    </Link>
                                </PaginationLink>
                            </PaginationItem>
                        ))}

                        {/* Next Page */}
                        <PaginationItem>
                            {nextUrl ? (
                                <PaginationNext asChild>
                                    <Link href={nextUrl} preserveState>
                                        <span className="hidden sm:block">Next</span>
                                        <ChevronRightIcon />
                                    </Link>
                                </PaginationNext>
                            ) : (
                                <PaginationNext
                                    className="opacity-50 cursor-not-allowed"
                                    aria-disabled="true"
                                    onClick={(e) => e.preventDefault()}
                                />
                            )}
                        </PaginationItem>
                    </div>
                )}
            </PaginationContent>
        </Pagination>
    );
}
