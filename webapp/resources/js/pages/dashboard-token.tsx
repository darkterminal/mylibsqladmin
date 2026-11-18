import { AppTooltip } from "@/components/app-tooltip";
import { ModalCreateToken } from "@/components/modals/modal-create-token";
import { PaginationControls } from "@/components/pagination-controls";
import TableTokenManagement from "@/components/tables/token-management";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import { Input } from "@/components/ui/input";
import AppLayout from "@/layouts/app-layout";
import {
    AllowedUser,
    PaginatedResults,
    type BreadcrumbItem,
    type MostUsedDatabaseProps,
    type UserDatabaseTokenProps
} from "@/types";
import { Head, router } from "@inertiajs/react";
import { KeyRound, Plus, Search, X } from "lucide-react";
import { useEffect, useState } from "react";

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Tokens',
        href: '/dashboard/tokens',
    }
];

export default function DatabaseToken({
    allUsers,
    mostUsedDatabases,
    userDatabaseTokens
}: {
    allUsers: AllowedUser[],
    mostUsedDatabases: MostUsedDatabaseProps[],
    userDatabaseTokens: PaginatedResults<UserDatabaseTokenProps>
}) {
    const [search, setSearch] = useState(() => {
        if (typeof window === 'undefined') return '';
        return new URLSearchParams(window.location.search).get('search') ?? '';
    });

    // Debounced server-side search — keeps pagination honest across pages.
    useEffect(() => {
        const handle = setTimeout(() => {
            const query = search.trim();
            router.visit(query ? `/dashboard/tokens?search=${encodeURIComponent(query)}` : '/dashboard/tokens', {
                only: ['userDatabaseTokens'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            });
        }, 350);

        return () => clearTimeout(handle);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    const isEmpty = userDatabaseTokens.total === 0 && search.trim() === '';

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tokens" />
            <div className="flex h-full min-w-0 flex-1 p-4">
                <Card className="min-w-0 flex-1">
                    <CardHeader className="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <h2 className="text-2xl font-semibold tracking-tight">Database Tokens</h2>
                            <p className="text-sm text-muted-foreground">
                                Credential pairs for this team · {userDatabaseTokens.total} token{userDatabaseTokens.total === 1 ? '' : 's'}
                            </p>
                        </div>
                        <div className="flex flex-wrap items-center gap-2">
                            <div className="relative">
                                <Search className="pointer-events-none absolute left-2.5 top-1/2 h-4 w-4 -translate-y-1/2 text-muted-foreground" aria-hidden="true" />
                                <Input
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Search name, database, user…"
                                    className="h-9 w-56 pl-8 sm:w-64"
                                    aria-label="Search tokens"
                                />
                                {search && (
                                    <button
                                        type="button"
                                        onClick={() => setSearch('')}
                                        className="absolute right-2 top-1/2 -translate-y-1/2 text-muted-foreground transition-colors hover:text-foreground"
                                        aria-label="Clear search"
                                    >
                                        <X className="h-4 w-4" />
                                    </button>
                                )}
                            </div>
                            <ModalCreateToken mostUsedDatabases={mostUsedDatabases} users={allUsers}>
                                <Button variant="default">
                                    <AppTooltip text="Generate New Token">
                                        <>
                                            <Plus className="h-4 w-4" />
                                            <span className="hidden sm:inline">Generate New Token</span>
                                            <span className="sm:hidden">Generate</span>
                                        </>
                                    </AppTooltip>
                                </Button>
                            </ModalCreateToken>
                        </div>
                    </CardHeader>
                    <CardContent className="min-w-0">
                        {isEmpty ? (
                            <div className="flex h-80 flex-col items-center justify-center gap-3 text-center">
                                <div className="rounded-full bg-muted p-4">
                                    <KeyRound className="h-8 w-8 text-muted-foreground" aria-hidden="true" />
                                </div>
                                <h3 className="text-lg font-semibold">No tokens yet</h3>
                                <p className="max-w-md text-sm text-muted-foreground">
                                    Tokens let applications connect to your databases. A full-access and a
                                    read-only pair is generated — you see them once, at creation.
                                </p>
                                <ModalCreateToken mostUsedDatabases={mostUsedDatabases} users={allUsers}>
                                    <Button>
                                        <Plus className="h-4 w-4" aria-hidden="true" />
                                        Generate your first token
                                    </Button>
                                </ModalCreateToken>
                            </div>
                        ) : (
                            <>
                                <TableTokenManagement userDatabaseTokens={userDatabaseTokens} />
                                <PaginationControls pagination={userDatabaseTokens} />
                            </>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
