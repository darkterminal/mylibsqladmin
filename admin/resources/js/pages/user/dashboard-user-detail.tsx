"use client"

import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card"
import { Separator } from "@/components/ui/separator"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { useInitials } from "@/hooks/use-initials"
import AppLayout from "@/layouts/app-layout"
import { BreadcrumbItem } from "@/types"
import { Head, Link, router } from "@inertiajs/react"
import { ArrowLeftIcon, CalendarClock, Mail, Shield, Users } from "lucide-react"
import { useState } from "react"
import { UserDataTable } from "./datatable/user-columns"

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Users',
        href: '/dashboard/users',
    },
    {
        title: 'User Detail',
        href: '#',
    }
]

export default function DashboardUserDetail({ userData }: { userData: UserDataTable }) {
    const getInitials = useInitials();
    const [activeTab, setActiveTab] = useState("overview")

    const formatDate = (dateString: string) => {
        return new Date(dateString).toLocaleDateString("en-US", {
            year: "numeric",
            month: "long",
            day: "numeric",
            hour: "2-digit",
            minute: "2-digit",
        })
    }

    const handleDeactivateAccount = () => {
        router.put(route('user.deactivate', userData.id))
    }

    const handleReactivateAccount = () => {
        router.put(route('user.reactivate', userData.id))
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="User Detail" />
            <div className="container mx-auto py-8 max-w-5xl">
                <div className="flex flex-col gap-6">
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="text-2xl font-bold">User Detail</h1>
                            <p className="text-muted-foreground text-sm">View and manage user details</p>
                        </div>
                        <Button variant="default" onClick={() => router.get(route('dashboard.users'))}>
                            <ArrowLeftIcon className="h-4 w-4" />
                            <span>Back</span>
                        </Button>
                    </div>
                    <Card>
                        <CardHeader className="pb-4">
                            <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                                <div className="flex items-center gap-4">
                                    <Avatar className="h-20 w-20 border-4 border-background">
                                        <AvatarImage
                                            src={undefined}
                                            alt={userData.name}
                                        />
                                        <AvatarFallback className="text-xl">{getInitials(userData.name)}</AvatarFallback>
                                    </Avatar>
                                    <div>
                                        <div className="flex items-center gap-2">
                                            <CardTitle className="text-2xl">{userData.name}</CardTitle>
                                            <Badge variant="outline" className="bg-green-50 text-green-700 hover:bg-green-50 border-green-200">
                                                Active
                                            </Badge>
                                        </div>
                                        <CardDescription className="text-base">@{userData.username}</CardDescription>
                                    </div>
                                </div>
                                <div className="flex gap-2">
                                    <Button variant="outline" asChild>
                                        <Link href={route('user.edit', userData.id)}>Edit Profile</Link>
                                    </Button>
                                </div>
                            </div>
                        </CardHeader>
                        <CardContent>
                            <Tabs defaultValue="overview" value={activeTab} onValueChange={setActiveTab} className="w-full">
                                <TabsList className="grid grid-cols-3 w-full max-w-md">
                                    <TabsTrigger value="overview">Overview</TabsTrigger>
                                    <TabsTrigger value="teams">Teams</TabsTrigger>
                                    <TabsTrigger value="roles">Roles</TabsTrigger>
                                </TabsList>

                                <TabsContent value="overview" className="pt-6">
                                    <div className="grid gap-6">
                                        <div className="grid gap-3">
                                            <h3 className="text-lg font-medium">Contact Information</h3>
                                            <div className="grid gap-2">
                                                <div className="flex items-center gap-2 text-muted-foreground">
                                                    <Mail className="h-4 w-4" />
                                                    <span>{userData.email}</span>
                                                </div>
                                            </div>
                                        </div>

                                        <Separator />

                                        <div className="grid gap-3">
                                            <h3 className="text-lg font-medium">Account Details</h3>
                                            <div className="grid gap-2">
                                                <div className="flex items-center gap-2 text-muted-foreground">
                                                    <CalendarClock className="h-4 w-4" />
                                                    <span>Created: {formatDate(userData.created_at)}</span>
                                                </div>
                                                <div className="flex items-center gap-2 text-muted-foreground">
                                                    <CalendarClock className="h-4 w-4" />
                                                    <span>Last Updated: {formatDate(userData.updated_at)}</span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </TabsContent>

                                <TabsContent value="teams" className="pt-6">
                                    <div className="grid gap-4">
                                        <h3 className="text-lg font-medium">Team Memberships</h3>
                                        <div className="grid gap-4">
                                            {userData.teams.map((team) => (
                                                <Card key={team.id} className="overflow-hidden">
                                                    <div className="flex items-center p-4 bg-muted/50">
                                                        <div className="flex items-center gap-3">
                                                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                                                                <Users className="h-5 w-5 text-primary" />
                                                            </div>
                                                            <div>
                                                                <h4 className="font-medium">{team.name}</h4>
                                                                <p className="text-sm text-muted-foreground">{team.description}</p>
                                                            </div>
                                                        </div>
                                                        <Badge className="ml-auto">{team.permission_level}</Badge>
                                                    </div>
                                                </Card>
                                            ))}
                                        </div>
                                    </div>
                                </TabsContent>

                                <TabsContent value="roles" className="pt-6">
                                    <div className="grid gap-4">
                                        <h3 className="text-lg font-medium">User Roles</h3>
                                        <div className="grid gap-4">
                                            {userData.roles.map((role) => (
                                                <Card key={role.id} className="overflow-hidden">
                                                    <div className="flex items-center p-4 bg-muted/50">
                                                        <div className="flex items-center gap-3">
                                                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-primary/10">
                                                                <Shield className="h-5 w-5 text-primary" />
                                                            </div>
                                                            <div>
                                                                <h4 className="font-medium">{role.name}</h4>
                                                                <p className="text-sm text-muted-foreground">Assigned: {formatDate(role.created_at)}</p>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </Card>
                                            ))}
                                        </div>
                                    </div>
                                </TabsContent>
                            </Tabs>
                        </CardContent>
                        <CardFooter className="flex justify-between border-t pt-6">
                            <Button
                                variant="outline"
                                className={userData.is_active ? 'text-destructive border-destructive/20 hover:bg-destructive/10 hover:text-destructive' : 'text-muted-foreground border-muted-foreground/20 hover:bg-muted-foreground/10 hover:text-muted-foreground'}
                                onClick={userData.is_active ? handleDeactivateAccount : handleReactivateAccount}
                            >
                                {userData.is_active ? 'Deactivate' : 'Reactivate'} Account
                            </Button>
                            <Button variant="outline" onClick={() => router.get(route('user.activities', userData.id))}>View Activity Log</Button>
                        </CardFooter>
                    </Card>
                </div>
            </div>
        </AppLayout>
    )
}
