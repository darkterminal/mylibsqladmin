import { Avatar, AvatarFallback, AvatarImage } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from "@/components/ui/card"
import { Separator } from "@/components/ui/separator"
import AppLayout from "@/layouts/app-layout"
import { BreadcrumbItem } from "@/types"
import { Head, Link, router, useForm } from '@inertiajs/react'
import { ArrowLeft, Loader2 } from "lucide-react"
import { toast } from "sonner"
import { UserDataTable } from './datatable/user-columns'
import BasicInformationForm from "./edit-form/basic-information"
import UserRoleForm from "./edit-form/role-user"
import TeamMembershipsForm from "./edit-form/team-members"

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
        title: 'User Edit',
        href: '#',
    }
]

export type UserFormProps = Record<string, any> & {
    name: string,
    username: string,
    email: string,
    teamSelections: number[],
    teamPermissions: Record<string, string>,
    roleSelections: string
}

export default function EditUserForm({
    user,
    availableTeams,
    availableRoles,
    permissionLevels
}: {
    user: UserDataTable,
    availableTeams: { id: number, name: string, description: string, permission_level: string }[],
    availableRoles: { id: number, name: string, created_at: string }[],
    permissionLevels: { id: string, name: string }[]
}) {
    const { data, setData, put, processing, errors } = useForm<UserFormProps>({
        name: user.name,
        username: user.username,
        email: user.email,
        teamSelections: user.teams.map(team => team.id),
        teamPermissions: user.teams.reduce((acc, team) => {
            const level = permissionLevels.find(l => l.name === team.permission_level);
            return {
                ...acc,
                [team.id]: level?.id || 'member'
            };
        }, {}),
        roleSelections: String(user.roles[0].id),
    });

    const getInitials = (name: string) => {
        return name
            .split(" ")
            .map(part => part[0])
            .join("")
            .toUpperCase()
    }

    const handleSubmit = (e: React.FormEvent<HTMLFormElement>) => {
        e.preventDefault()
        put(route('user.update', user.id), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('User updated successfully', {
                    duration: 1500,
                    onAutoClose(toast) {
                        router.visit(window.location.href, {
                            preserveScroll: true
                        });
                    },
                })
            },
        })
    }

    const handleTeamSelection = (teamId: number, checked: boolean) => {
        const selections = [...data.teamSelections]
        const defaultPermission = permissionLevels.find(l => l.name === 'Member')?.id || 'member'

        if (checked) {
            if (!selections.includes(teamId)) {
                selections.push(teamId)
                setData('teamPermissions', {
                    ...data.teamPermissions,
                    [teamId]: defaultPermission
                })
            }
        } else {
            const index = selections.indexOf(teamId)
            if (index !== -1) selections.splice(index, 1)
            const { [teamId]: _, ...remaining } = data.teamPermissions
            setData('teamPermissions', remaining)
        }

        setData('teamSelections', selections)
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Edit User" />
            <div className="container mx-auto py-6 max-w-3xl">
                <div className="flex items-center justify-between">
                    <Button asChild variant="ghost" className="mb-6 pl-0 flex items-center gap-2">
                        <Link href={route('user.show', user.id)}>
                            <ArrowLeft className="h-4 w-4" />
                            Back to User Details
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-4">
                            <Avatar className="h-16 w-16 border-4 border-background">
                                <AvatarImage
                                    src={undefined}
                                    alt={user.name}
                                />
                                <AvatarFallback className="text-lg">{getInitials(user.name)}</AvatarFallback>
                            </Avatar>
                            <div>
                                <CardTitle className="text-2xl">Edit User Profile</CardTitle>
                                <CardDescription className="text-base">Update user information, teams, and roles</CardDescription>
                            </div>
                        </div>
                    </CardHeader>

                    <form onSubmit={handleSubmit} autoComplete="off">
                        <CardContent className="space-y-6">
                            {/* Basic Information */}
                            <BasicInformationForm
                                data={data}
                                setData={setData}
                                errors={errors}
                            />

                            <Separator />

                            {/* Team Memberships */}
                            <TeamMembershipsForm
                                availableTeams={availableTeams}
                                permissionLevels={permissionLevels}
                                data={data}
                                setData={setData}
                                errors={errors}
                                handleTeamSelection={handleTeamSelection}
                            />

                            <Separator />

                            {/* User Roles */}
                            <UserRoleForm
                                availableRoles={availableRoles}
                                data={data}
                                setData={setData}
                                errors={errors}
                            />
                        </CardContent>

                        <CardFooter className="flex justify-between border-t pt-6">
                            <Button variant="outline" type="button" asChild>
                                <Link href={route('user.show', user.id)}>Cancel</Link>
                            </Button>
                            <Button type="submit" disabled={processing}>
                                {processing && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                                Save Changes
                            </Button>
                        </CardFooter>
                    </form>
                </Card>
            </div>
        </AppLayout>
    )
}

