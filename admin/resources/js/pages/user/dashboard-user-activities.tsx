import { Badge } from "@/components/ui/badge";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuTrigger } from "@/components/ui/dropdown-menu";
import { Input } from "@/components/ui/input";
import { Separator } from "@/components/ui/separator";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table";
import AppLayout from "@/layouts/app-layout";
import { ActivityLog, ActivityType, BreadcrumbItem } from "@/types";
import { Head } from "@inertiajs/react";
import { format } from "date-fns";
import { ChevronDown, Clock, Filter, MapPin, Monitor, Search } from "lucide-react";
import { useState } from "react";

const getActivityTypeColor = (type: string) => {
    switch (type) {
        case ActivityType.LOGIN:
        case ActivityType.LOGOUT:
            return "bg-green-100 text-green-800"
        case ActivityType.TEAM_UPDATE:
        case ActivityType.TEAM_CREATE:
        case ActivityType.TEAM_DELETE:
        case ActivityType.TEAM_MEMBER_CREATE:
        case ActivityType.TEAM_MEMBER_DELETE:
        case ActivityType.TEAM_MEMBER_UPDATE:
            return "bg-purple-100 text-purple-800"
        case ActivityType.GROUP_DATABASE_CREATE:
        case ActivityType.GROUP_DATABASE_DELETE:
        case ActivityType.GROUP_DATABASE_UPDATE:
        case ActivityType.GROUP_DATABASE_TOKEN_CREATE:
        case ActivityType.GROUP_DATABASE_TOKEN_DELETE:
        case ActivityType.GROUP_DATABASE_TOKEN_UPDATE:
            return "bg-blue-100 text-blue-800"
        case ActivityType.DATABASE_CREATE:
        case ActivityType.DATABASE_DELETE:
        case ActivityType.DATABASE_UPDATE:
        case ActivityType.DATABASE_TOKEN_CREATE:
        case ActivityType.DATABASE_TOKEN_DELETE:
        case ActivityType.DATABASE_TOKEN_UPDATE:
            return "bg-yellow-100 text-yellow-800"
        case ActivityType.USER_CREATE:
        case ActivityType.USER_DELETE:
        case ActivityType.USER_UPDATE:
        case ActivityType.USER_RESTORE:
        case ActivityType.USER_DEACTIVATE:
        case ActivityType.USER_REACTIVATE:
        case ActivityType.USER_FORCE_DELETE:
            return "bg-red-100 text-red-800"
        default:
            return "bg-gray-100 text-gray-800"
    }
}

// Helper function to format activity type for display
const formatActivityType = (type: string) => {
    return type
        .split("_")
        .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
        .join(" ")
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: "Dashboard",
        href: "/dashboard",
    },
    {
        title: "Users",
        href: "/dashboard/users",
    },
    {
        title: "Activities",
        href: "#",
    },
]

export default function DashboardUserActivities({ activities }: { activities: ActivityLog[] }) {
    const [searchTerm, setSearchTerm] = useState("")
    const [selectedType, setSelectedType] = useState<string | null>(null)
    const [viewMode, setViewMode] = useState<"table" | "card">("table")

    // Get unique activity types for filter
    const activityTypes = Array.from(new Set(activities.map((log) => log.type)))

    // Filter logs based on search term and selected type
    const filteredLogs = activities.filter((log) => {
        const matchesSearch =
            log.description.toLowerCase().includes(searchTerm.toLowerCase()) ||
            log.user.name.toLowerCase().includes(searchTerm.toLowerCase()) ||
            log.user.username.toLowerCase().includes(searchTerm.toLowerCase()) ||
            log.type.toLowerCase().includes(searchTerm.toLowerCase())

        const matchesType = selectedType ? log.type === selectedType : true

        return matchesSearch && matchesType
    })

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Users" />
            <div className="container mx-auto py-6">
                <div className="flex flex-col space-y-4">
                    <div className="flex items-center justify-between">
                        <h1 className="text-2xl font-bold">User Activity Logs</h1>
                        <div className="flex items-center space-x-2">
                            <Button
                                variant={viewMode === "table" ? "default" : "outline"}
                                size="sm"
                                onClick={() => setViewMode("table")}
                            >
                                Table View
                            </Button>
                            <Button variant={viewMode === "card" ? "default" : "outline"} size="sm" onClick={() => setViewMode("card")}>
                                Card View
                            </Button>
                        </div>
                    </div>

                    <div className="flex flex-col sm:flex-row gap-4">
                        <div className="relative flex-1">
                            <Search className="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                            <Input
                                placeholder="Search activities..."
                                className="pl-8"
                                value={searchTerm}
                                onChange={(e) => setSearchTerm(e.target.value)}
                            />
                        </div>

                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" className="flex items-center gap-2">
                                    <Filter className="h-4 w-4" />
                                    {selectedType ? formatActivityType(selectedType) : "Filter by type"}
                                    <ChevronDown className="h-4 w-4" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end" className="w-[200px] max-h-[400px] overflow-y-auto">
                                <DropdownMenuItem onClick={() => setSelectedType(null)}>All Types</DropdownMenuItem>
                                {activityTypes.map((type) => (
                                    <DropdownMenuItem key={type} onClick={() => setSelectedType(type)}>
                                        {formatActivityType(type)}
                                    </DropdownMenuItem>
                                ))}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>

                    {viewMode === "table" ? (
                        <div className="rounded-md border">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Type</TableHead>
                                        <TableHead>User</TableHead>
                                        <TableHead>Description</TableHead>
                                        <TableHead>IP Address</TableHead>
                                        <TableHead>Date & Time</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {filteredLogs.length === 0 ? (
                                        <TableRow>
                                            <TableCell colSpan={6} className="text-center py-4">
                                                No activity logs found
                                            </TableCell>
                                        </TableRow>
                                    ) : (
                                        filteredLogs.map((log) => (
                                            <TableRow key={log.id}>
                                                <TableCell>
                                                    <Badge className={`${getActivityTypeColor(log.type)}`}>{formatActivityType(log.type)}</Badge>
                                                </TableCell>
                                                <TableCell>
                                                    <div className="flex flex-col">
                                                        <span className="font-medium">{log.user.name}</span>
                                                        <span className="text-xs text-muted-foreground">@{log.user.username}</span>
                                                    </div>
                                                </TableCell>
                                                <TableCell>{log.description}</TableCell>
                                                <TableCell className="font-mono text-xs">{log.metadata.ip}</TableCell>
                                                <TableCell>
                                                    {format(new Date(log.timestamp), "MMM d, yyyy")}
                                                    <div className="text-xs text-muted-foreground">{format(new Date(log.timestamp), "h:mm a")}</div>
                                                </TableCell>
                                            </TableRow>
                                        ))
                                    )}
                                </TableBody>
                            </Table>
                        </div>
                    ) : (
                        <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            {filteredLogs.length === 0 ? (
                                <div className="col-span-full text-center py-8">No activity logs found</div>
                            ) : (
                                filteredLogs.map((log) => (
                                    <Card key={log.id} className="overflow-hidden">
                                        <CardHeader className="pb-2">
                                            <div className="flex justify-between items-start">
                                                <Badge className={`${getActivityTypeColor(log.type)}`}>{formatActivityType(log.type)}</Badge>
                                            </div>
                                            <CardTitle className="text-lg mt-2">{log.user.name}</CardTitle>
                                            <CardDescription>@{log.user.username}</CardDescription>
                                        </CardHeader>
                                        <CardContent className="pb-3">
                                            <p className="text-sm mb-4">{log.description}</p>
                                            <Separator className="my-3" />
                                            <div className="grid grid-cols-2 gap-2 text-sm">
                                                <div className="flex items-center gap-1.5">
                                                    <Clock className="h-4 w-4 text-muted-foreground" />
                                                    <span>{format(new Date(log.timestamp), "MMM d, yyyy h:mm a")}</span>
                                                </div>
                                                <div className="flex items-center gap-1.5">
                                                    <MapPin className="h-4 w-4 text-muted-foreground" />
                                                    <span className="font-mono text-xs">{log.metadata.ip}</span>
                                                </div>
                                                <div className="flex items-center gap-1.5 col-span-2 truncate">
                                                    <Monitor className="h-4 w-4 flex-shrink-0 text-muted-foreground" />
                                                    <span className="truncate text-xs">{log.metadata.device}</span>
                                                </div>
                                            </div>
                                        </CardContent>
                                    </Card>
                                ))
                            )}
                        </div>
                    )}
                </div>
            </div>
        </AppLayout>
    )
}
