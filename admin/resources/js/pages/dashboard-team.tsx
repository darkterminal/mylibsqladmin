"use client"

import { ModalCreateTeam } from "@/components/modals/modal-create-team"
import TeamCard from "@/components/team/team-card"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import AppLayout from "@/layouts/app-layout"
import { usePermission } from "@/lib/auth"
import { BreadcrumbItem, Team } from "@/types"
import { Head } from "@inertiajs/react"
import { Search, Users } from "lucide-react"
import { useEffect, useState } from "react"

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
    const [currentTeamId, setCurrentTeamId] = useState<string | null>(null)

    useEffect(() => {
        setCurrentTeamId(localStorage.getItem('currentTeamId'))
    }, [])

    const sortedAndFilteredTeams = teams
        .filter(team =>
            team.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            team.description.toLowerCase().includes(searchQuery.toLowerCase())
        )
        .sort((a, b) => {
            if (String(a.id) === currentTeamId) return -1
            if (String(b.id) === currentTeamId) return 1
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
                    <div className="flex items-center gap-2 w-full md:w-auto">
                        <div className="relative w-full md:w-[300px]">
                            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input
                                type="search"
                                placeholder="Search teams..."
                                className="pl-8 w-full"
                                value={searchQuery}
                                onChange={(e) => setSearchQuery(e.target.value)}
                            />
                        </div>
                        {can('manage-teams') && (
                            <ModalCreateTeam
                                trigger={
                                    <Button>
                                        <Users className="mr-2 h-4 w-4" />
                                        New Team
                                    </Button>
                                }
                                onSave={(team) => console.log(team)}
                                validate={(team) => team.name.length < 3 ? "Name must be at least 3 characters" : null}
                            />
                        )}
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {sortedAndFilteredTeams.map((team) => (
                        <TeamCard
                            key={team.id}
                            team={team}
                            isCurrent={String(team.id) === currentTeamId}
                            totalTeams={teams.length}
                        />
                    ))}
                </div>
            </div>
        </AppLayout>
    )
}
