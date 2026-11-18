"use client"

import { ModalCreateTeam } from "@/components/modals/modal-create-team"
import TeamsTable from "@/components/tables/teams"
import TeamCard from "@/components/team/team-card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { ToggleGroup, ToggleGroupItem } from "@/components/ui/toggle-group"
import AppLayout from "@/layouts/app-layout"
import { usePermission } from "@/lib/auth"
import { BreadcrumbItem, Team, TeamForm } from "@/types"
import { Head, router } from "@inertiajs/react"
import { Grid3X3, List, Search, SearchX, Trash, Users, X } from "lucide-react"
import { useState } from "react"
import { useCurrentTeamId } from "@/lib/current-team"

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Teams',
        href: '/dashboard/teams',
    }
];

export default function DashboardTeam({ teams }: { teams: Team[] }) {
    const { can } = usePermission();
    const [searchQuery, setSearchQuery] = useState<string>("")
    const [viewMode, setViewMode] = useState("table")
    
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

    const sortedAndFilteredTeams = teams
        .filter(team =>
            team.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            team.description.toLowerCase().includes(searchQuery.toLowerCase())
        )
        .sort((a, b) => {
            if (a.id === currentTeamId) return -1
            if (b.id === currentTeamId) return 1
            return a.name.localeCompare(b.name)
        })

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Teams" />
            <div className="container mx-auto p-6 space-y-6">
                <div className="flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight">Team Dashboard</h1>
                        <p className="text-muted-foreground">Manage your teams, groups, and databases</p>
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
                                {can('delete-teams') && (
                                    <Button onClick={() => router.visit(route('teams.archives'))}>
                                        <Trash className="h-4 w-4" />
                                        Team Archives
                                    </Button>
                                )}
                            </>
                        )}
                    </div>
                </div>

                {sortedAndFilteredTeams.length === 0 ? (
                    <div className="flex h-64 flex-col items-center justify-center gap-3 rounded-md border text-center">
                        <SearchX className="h-8 w-8 text-muted-foreground" aria-hidden="true" />
                        <h2 className="text-lg font-semibold">No teams match “{searchQuery}”</h2>
                        <p className="max-w-sm text-sm text-muted-foreground">Try a different name or description, or create a new team instead.</p>
                        <Button variant="outline" onClick={() => setSearchQuery('')}>
                            <X className="h-4 w-4" aria-hidden="true" />
                            Clear search
                        </Button>
                    </div>
                ) : viewMode === "table" ? (
                    <TeamsTable teams={sortedAndFilteredTeams} />
                ) : (
                    <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        {sortedAndFilteredTeams.map((team) => (
                            <TeamCard
                                key={team.id}
                                team={team}
                                isCurrent={team.id === currentTeamId}
                                totalTeams={teams.length}
                            />
                        ))}
                    </div>
                )}
            </div>
        </AppLayout>
    )
}
