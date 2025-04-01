"use client"

import ButtonOpenDatabaseStudio from "@/components/button-actions/action-open-database-studio"
import { ModalCreateGroupOnly } from "@/components/modals/modal-create-group-only"
import { ModalCreateTeam } from "@/components/modals/modal-create-team"
import { ModalEditTeam } from "@/components/modals/modal-edit-team"
import { ModalManageMembers } from "@/components/modals/modal-manage-members"
import { Avatar } from "@/components/ui/avatar"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card"
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Input } from "@/components/ui/input"
import { ScrollArea } from "@/components/ui/scroll-area"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { useInitials } from "@/hooks/use-initials"
import AppLayout from "@/layouts/app-layout"
import { cn } from "@/lib/utils"
import { BreadcrumbItem, Member } from "@/types"
import { Head } from "@inertiajs/react"
import { Activity, ChevronDown, ChevronRight, Cylinder, Database, File, FolderClosed, GitBranch, MoreHorizontal, Search, Users } from "lucide-react"
import { useState } from "react"

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

// Types
export interface Database {
    id: number
    name: string
    type: string
    lastActivity: string
}

export interface Group {
    id: number
    name: string
    databases: Database[]
}

export interface RecentActivity {
    id: number
    user: string
    action: string
    database: string
    time: string
}

export interface Team {
    id: number
    name: string
    description: string
    members: number
    groups: Group[]
    team_members: Member[]
    recentActivity: RecentActivity[]
}

export default function DashboardTeam({ teams }: { teams: Team[] }) {
    const [searchQuery, setSearchQuery] = useState<string>("")

    // Filter teams based on search query
    const filteredTeams = teams.filter(
        (team) =>
            team.name.toLowerCase().includes(searchQuery.toLowerCase()) ||
            team.description.toLowerCase().includes(searchQuery.toLowerCase()),
    )

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
                    </div>
                </div>

                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    {filteredTeams.map((team) => (
                        <TeamCard key={team.id} team={team} />
                    ))}
                </div>
            </div>
        </AppLayout>
    )
}

interface TeamCardProps {
    team: Team
}

function TeamCard({ team }: TeamCardProps) {
    const getInitials = useInitials();
    return (
        <Card className="h-full flex flex-col">
            <CardHeader className="pb-2">
                <div className="flex justify-between items-start">
                    <div className="space-y-1">
                        <CardTitle className="flex items-center">
                            {team.name}
                            <Badge variant="outline" className="ml-2">
                                <Users className="h-3 w-3 mr-1" />
                                {team.members}
                            </Badge>
                        </CardTitle>
                        <CardDescription>{team.description}</CardDescription>
                    </div>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button variant="ghost" size="icon" className="h-8 w-8">
                                <MoreHorizontal className="h-4 w-4" />
                                <span className="sr-only">Open menu</span>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end">
                            <DropdownMenuLabel>Actions</DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem asChild>
                                <ModalEditTeam
                                    trigger={
                                        <Button variant="ghost" size={"sm"} className="flex w-full justify-start">
                                            Edit team
                                        </Button>
                                    }
                                    onSave={(team) => console.log(team)}
                                    initValues={team}
                                />
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <ModalManageMembers
                                    trigger={
                                        <Button variant="ghost" size={"sm"} className="flex w-full justify-start">
                                            Manage members
                                        </Button>
                                    }
                                    members={team.team_members}
                                    onAddMember={(member) => console.log(member)}
                                    onRemoveMember={(memberId) => console.log(memberId)}
                                    onUpdateRole={(memberId, role) => console.log(memberId, role)}
                                />
                            </DropdownMenuItem>
                            <DropdownMenuItem asChild>
                                <ModalCreateGroupOnly
                                    trigger={
                                        <Button variant="ghost" size={"sm"} className="flex w-full justify-start">
                                            Add group
                                        </Button>
                                    }
                                    onSave={(groupName) => console.log(groupName)}
                                />
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem className="text-destructive">Delete team</DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>
            </CardHeader>
            <CardContent className="flex-grow">
                <Tabs defaultValue="groups" className="h-full flex flex-col">
                    <TabsList className="grid w-full grid-cols-2">
                        <TabsTrigger value="groups">
                            <FolderClosed className="h-4 w-4 mr-2" />
                            Groups
                        </TabsTrigger>
                        <TabsTrigger value="activity">
                            <Activity className="h-4 w-4 mr-2" />
                            Activity
                        </TabsTrigger>
                    </TabsList>
                    <TabsContent value="groups" className="flex-grow mt-4 space-y-4">
                        <ScrollArea className="h-[250px]">
                            <GroupTree groups={team.groups} />
                        </ScrollArea>
                    </TabsContent>
                    <TabsContent value="activity" className="flex-grow mt-4">
                        <ScrollArea className="h-[250px]">
                            <div className="space-y-4">
                                {team.recentActivity.map((activity) => (
                                    <div key={activity.id} className="flex items-start gap-3 text-sm">
                                        <Avatar className="flex items-center justify-center text-primary-foreground bg-primary">
                                            <span className="text-xs">{getInitials(activity.user)}</span>
                                        </Avatar>
                                        <div className="flex-1 space-y-1">
                                            <p className="font-medium leading-none">{activity.user}</p>
                                            <p className="text-xs text-muted-foreground">
                                                {activity.action} on <span className="font-medium">{activity.database}</span>
                                            </p>
                                        </div>
                                        <div className="text-xs text-muted-foreground whitespace-nowrap">{activity.time}</div>
                                    </div>
                                ))}
                            </div>
                        </ScrollArea>
                    </TabsContent>
                </Tabs>
            </CardContent>
            <CardFooter className="pt-2 border-t">
                <Button variant="outline" size="sm" className="w-full">
                    <Database className="h-3.5 w-3.5 mr-2" />
                    Add Database
                </Button>
            </CardFooter>
        </Card>
    )
}

function GroupTree({ groups }: { groups: Group[] }) {
    // Initialize all groups as expanded by default
    const [expandedGroups, setExpandedGroups] = useState<{
        [groupId: number]: boolean;
    }>(
        groups.reduce<{ [groupId: number]: boolean }>((acc, group) => {
            acc[group.id] = group.databases.length > 0
            return acc
        }, {}),
    )

    const toggleGroup = (groupId: number) => {
        setExpandedGroups((prev) => ({
            ...prev,
            [groupId]: !prev[groupId],
        }))
    }

    return (
        <div className="space-y-1">
            {groups.map((group) => (
                <div key={group.id} className="rounded-md overflow-hidden">
                    <button
                        onClick={() => toggleGroup(group.id)}
                        className="w-full flex items-center text-left p-2 hover:bg-muted/80 rounded-md transition-colors"
                    >
                        {expandedGroups[group.id] ? (
                            <ChevronDown className="h-4 w-4 mr-1 text-muted-foreground shrink-0" />
                        ) : (
                            <ChevronRight className="h-4 w-4 mr-1 text-muted-foreground shrink-0" />
                        )}
                        <FolderClosed className="h-4 w-4 mr-2 text-muted-foreground shrink-0" />
                        <span className="font-medium">{group.name}</span>
                        <Badge variant="outline" className="ml-2 text-xs">
                            {group.databases.length}
                        </Badge>
                    </button>

                    <div
                        className={cn(
                            "pl-7 space-y-1 mt-1 overflow-hidden transition-all duration-200",
                            expandedGroups[group.id] ? "max-h-96" : "max-h-0",
                        )}
                    >
                        {group.databases.map((db) => (
                            <div
                                key={db.id}
                                className="flex items-center justify-between text-sm p-2 rounded-md hover:bg-muted/80 transition-colors"
                            >
                                <div className="flex items-center">
                                    {db.type === "standalone" ? <Cylinder className="h-4 w-4 mr-2 text-muted-foreground shrink-0" /> : db.type == "schema" ? <File className="h-4 w-4 mr-2 text-muted-foreground shrink-0" /> : <GitBranch className="h-4 w-4 mr-2 text-muted-foreground shrink-0" />}
                                    <span>{db.name}</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge
                                        variant={
                                            db.type === "standalone" ? "default" : db.type === "schema" ? "secondary" : "outline"
                                        }
                                        className="text-xs"
                                    >
                                        {db.type}
                                    </Badge>
                                    <ButtonOpenDatabaseStudio databaseName={db.name} />
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            ))}
        </div>
    )
}
