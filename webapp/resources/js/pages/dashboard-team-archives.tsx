"use client"

import { ModalCreateTeam } from "@/components/modals/modal-create-team"
import { PaginationControls } from "@/components/pagination-controls"
import TeamsTable from "@/components/tables/teams"
import TeamCard from "@/components/team/team-card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { ToggleGroup, ToggleGroupItem } from "@/components/ui/toggle-group"
import AppLayout from "@/layouts/app-layout"
import { usePermission } from "@/lib/auth"
import { BreadcrumbItem, PaginatedResults, Team, TeamForm } from "@/types"
import { Head, router } from "@inertiajs/react"
import { ArrowLeft, Archive, Grid3X3, List, Search, SearchX, Users, X } from "lucide-react"
import { useEffect, useRef, useState } from "react"
import { useCurrentTeamId } from "@/lib/current-team"

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Teams',
        href: '/dashboard/teams',
    },
    {
        title: 'Team Archives',
        href: '/dashboard/teams/archives',
    }
];

export default function DashboardTeam({ teams }: { teams: PaginatedResults<Team> }) {
    const { can } = usePermission();
    const [searchQuery, setSearchQuery] = useState<string>(() => {
        if (typeof window === 'undefined') return '';
        return new URLSearchParams(window.location.search).get('search') ?? '';
    })
    // Small screens default to the card view — the dense table only works
    // once there is room for it; users can still flip the toggle.
    const [viewMode, setViewMode] = useState(() =>
        typeof window !== "undefined" && window.innerWidth < 768 ? "grid" : "table"
    )

    const persistSearch = (value: string) => {
        const query = value.trim();
        router.visit(query ? `/dashboard/teams/archives?search=${encodeURIComponent(query)}` : '/dashboard/teams/archives', {
            preserveState: true,
            preserveScroll: true,
            replace: true,
            only: ['teams'],
        });
    };

    const isFirstRender = useRef(true);

    useEffect(() => {
        if (isFirstRender.current) {
            isFirstRender.current = false;
            return;
        }

        const handle = window.setTimeout(() => persistSearch(searchQuery), 350);

        return () => window.clearTimeout(handle);
    }, [searchQuery])

    const handleOnSave = (team: TeamForm) => {
        router.post(route('team.create'), team, {
            onSuccess: () => {
                router.visit(window.location.href, {
                    preserveScroll: true
                })
            }
        })
    }

    const currentTeamId = useCurrentTeamId()
    const visibleTeams = teams.data

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Teams Archives" />
            <div className="container mx-auto p-6 space-y-6">
                <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Team Archives</h1>
                        <p className="text-muted-foreground">List of archived teams</p>
                    </div>
                    <div className="flex w-full flex-wrap items-center gap-2 md:w-auto">
                        <div className="relative w-full sm:w-[300px]">
                            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input
                                type="search"
                                placeholder="Search teams..."
                                className="pl-8 w-full"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                            />
                        </div>
                        <ToggleGroup type="single" value={viewMode} onValueChange={setViewMode}>
                            <ToggleGroupItem value="table" aria-label="Table view">
                                <List className="h-4 w-4" />
                            </ToggleGroupItem>
                            <ToggleGroupItem value="grid" aria-label="Grid view">
                                <Grid3X3 className="h-4 w-4" />
                            </ToggleGroupItem>
                        </ToggleGroup>
                        {can('manage-teams') && (
                            <>
                                <ModalCreateTeam
                                    trigger={
                                        <Button>
                                            <Users className="mr-2 h-4 w-4" />
                                            New Team
                                        </Button>
                                    }
                                    onSave={handleOnSave}
                                    validate={(team) => team.name.length < 3 ? "Name must be at least 3 characters" : null}
                                />
                                <Button onClick={() => router.visit(route('dashboard.teams'))}>
                                    <ArrowLeft className="h-4 w-4" />
                                    Team Dashboard
                                </Button>
                            </>
                        )}
                    </div>
                </div>

                {visibleTeams.length === 0 && (
                    searchQuery ? (
                        <div className="flex h-64 flex-col items-center justify-center gap-3 rounded-md border text-center">
                            <SearchX className="h-8 w-8 text-muted-foreground" aria-hidden="true" />
                            <h2 className="text-lg font-semibold">No teams match “{searchQuery}”</h2>
                            <p className="max-w-sm text-sm text-muted-foreground">Try a different name or description, or clear the search.</p>
                            <Button variant="outline" onClick={() => setSearchQuery('')}>
                                <X className="h-4 w-4" aria-hidden="true" />
                                Clear search
                            </Button>
                        </div>
                    ) : (
                        <div className="flex h-64 flex-col items-center justify-center gap-3 rounded-md border text-center">
                            <Archive className="h-8 w-8 text-muted-foreground" aria-hidden="true" />
                            <h2 className="text-lg font-semibold">No archived teams</h2>
                            <p className="max-w-sm text-sm text-muted-foreground">Teams you archive will appear here, with their groups and databases.</p>
                            <Button onClick={() => router.visit(route('dashboard.teams'))}>
                                <ArrowLeft className="h-4 w-4" aria-hidden="true" />
                                Back to teams dashboard
                            </Button>
                        </div>
                    )
                )}
                {visibleTeams.length > 0 && (viewMode === "table" ? (
                    <TeamsTable teams={visibleTeams} isArchived={true} />
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {visibleTeams.map((team) => (
                            <TeamCard
                                key={team.id}
                                team={team}
                                isCurrent={team.id === currentTeamId}
                                totalTeams={teams.total}
                                isArchived={true}
                            />
                        ))}
                    </div>
                ))}
                <PaginationControls pagination={teams} />
            </div>
        </AppLayout>
    )
}
