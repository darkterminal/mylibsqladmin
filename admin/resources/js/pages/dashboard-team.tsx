"use client"

import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
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
import AppLayout from "@/layouts/app-layout"
import { BreadcrumbItem } from "@/types"
import { Head } from "@inertiajs/react"
import { Activity, Database, FolderClosed, MoreHorizontal, Search, Users } from "lucide-react"
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
interface Database {
    id: number
    name: string
    type: string
    lastActivity: string
}

interface Group {
    id: number
    name: string
    databases: Database[]
}

interface RecentActivity {
    id: number
    user: string
    action: string
    database: string
    time: string
}

interface Team {
    id: number
    name: string
    description: string
    members: number
    groups: Group[]
    recentActivity: RecentActivity[]
}

// Mock data
const teams: Team[] = [
    {
        id: 1,
        name: "Engineering",
        description: "Product development team",
        members: 12,
        groups: [
            {
                id: 101,
                name: "Backend",
                databases: [
                    { id: 1001, name: "User Service DB", type: "standalone", lastActivity: "2 hours ago" },
                    { id: 1002, name: "Auth Service DB", type: "schema database", lastActivity: "1 day ago" },
                ],
            },
            {
                id: 102,
                name: "Frontend",
                databases: [{ id: 1003, name: "Analytics DB", type: "child database", lastActivity: "3 hours ago" }],
            },
        ],
        recentActivity: [
            { id: 1, user: "Alex Kim", action: "Updated schema", database: "User Service DB", time: "2 hours ago" },
            { id: 2, user: "Jamie Chen", action: "Added index", database: "Auth Service DB", time: "1 day ago" },
            { id: 3, user: "Taylor Wong", action: "Query optimization", database: "Analytics DB", time: "3 hours ago" },
        ],
    },
    {
        id: 2,
        name: "Marketing",
        description: "Growth and acquisition team",
        members: 8,
        groups: [
            {
                id: 201,
                name: "Content",
                databases: [{ id: 2001, name: "CMS Database", type: "standalone", lastActivity: "5 hours ago" }],
            },
            {
                id: 202,
                name: "Analytics",
                databases: [
                    { id: 2002, name: "Marketing Metrics", type: "schema database", lastActivity: "30 minutes ago" },
                    { id: 2003, name: "Campaign Data", type: "child database", lastActivity: "1 hour ago" },
                ],
            },
        ],
        recentActivity: [
            { id: 4, user: "Morgan Smith", action: "Created report", database: "Marketing Metrics", time: "30 minutes ago" },
            { id: 5, user: "Casey Johnson", action: "Updated campaign data", database: "Campaign Data", time: "1 hour ago" },
            { id: 6, user: "Riley Brown", action: "Content update", database: "CMS Database", time: "5 hours ago" },
        ],
    },
    {
        id: 3,
        name: "Finance",
        description: "Accounting and financial operations",
        members: 5,
        groups: [
            {
                id: 301,
                name: "Accounting",
                databases: [
                    { id: 3001, name: "General Ledger", type: "schema database", lastActivity: "1 day ago" },
                    { id: 3002, name: "Transactions DB", type: "child database", lastActivity: "4 hours ago" },
                ],
            },
        ],
        recentActivity: [
            { id: 7, user: "Jordan Lee", action: "Monthly reconciliation", database: "General Ledger", time: "1 day ago" },
            {
                id: 8,
                user: "Avery Garcia",
                action: "Transaction processing",
                database: "Transactions DB",
                time: "4 hours ago",
            },
        ],
    },
    {
        id: 4,
        name: "Product",
        description: "Product management and design",
        members: 10,
        groups: [
            {
                id: 401,
                name: "Design",
                databases: [{ id: 4001, name: "Asset Library", type: "standalone", lastActivity: "6 hours ago" }],
            },
            {
                id: 402,
                name: "Research",
                databases: [{ id: 4002, name: "User Research", type: "schema database", lastActivity: "2 days ago" }],
            },
        ],
        recentActivity: [
            { id: 9, user: "Quinn Martinez", action: "Added assets", database: "Asset Library", time: "6 hours ago" },
            { id: 10, user: "Reese Wilson", action: "Updated research data", database: "User Research", time: "2 days ago" },
        ],
    },
]

export default function DashboardTeam() {
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
                        <Button>
                            <Users className="mr-2 h-4 w-4" />
                            New Team
                        </Button>
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
                            <DropdownMenuItem>Edit team</DropdownMenuItem>
                            <DropdownMenuItem>Manage members</DropdownMenuItem>
                            <DropdownMenuItem>Add group</DropdownMenuItem>
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
                        {team.groups.map((group) => (
                            <div key={group.id} className="space-y-2">
                                <div className="flex items-center">
                                    <FolderClosed className="h-4 w-4 mr-2 text-muted-foreground" />
                                    <h3 className="font-medium">{group.name}</h3>
                                </div>
                                <div className="pl-6 space-y-2">
                                    {group.databases.map((db) => (
                                        <div key={db.id} className="flex items-center justify-between text-sm p-2 rounded-md bg-muted/50">
                                            <div className="flex items-center">
                                                <Database className="h-3.5 w-3.5 mr-2 text-primary" />
                                                <span>{db.name}</span>
                                            </div>
                                            <div className="flex items-center">
                                                <Badge variant="outline" className="text-xs">
                                                    {db.type}
                                                </Badge>
                                            </div>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        ))}
                    </TabsContent>
                    <TabsContent value="activity" className="flex-grow mt-4">
                        <ScrollArea className="h-[200px]">
                            <div className="space-y-4">
                                {team.recentActivity.map((activity) => (
                                    <div key={activity.id} className="flex items-start gap-3 text-sm">
                                        <Avatar className="h-6 w-6">
                                            <AvatarImage src={`/placeholder.svg?height=32&width=32`} alt={activity.user} />
                                            <AvatarFallback>
                                                {activity.user
                                                    .split(" ")
                                                    .map((n) => n[0])
                                                    .join("")}
                                            </AvatarFallback>
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

