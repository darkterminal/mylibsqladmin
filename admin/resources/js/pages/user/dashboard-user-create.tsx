import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card'
import { Input } from '@/components/ui/input'
import { Separator } from '@/components/ui/separator'
import AppLayout from '@/layouts/app-layout'
import { BreadcrumbItem } from '@/types'
import { Head, Link, router, useForm } from '@inertiajs/react'
import { ArrowLeft, Loader2 } from "lucide-react"
import { toast } from 'sonner'
import BasicInformationForm from './edit-form/basic-information'
import UserRoleForm from './edit-form/role-user'
import TeamMembershipsForm from './edit-form/team-members'

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
        title: 'Create User',
        href: '#',
    }
]

export default function CreateUserForm({
    availableTeams,
    availableRoles,
    permissionLevels
}: {
    availableTeams: { id: number, name: string, description: string, permission_level: string }[],
    availableRoles: { id: number, name: string, created_at: string }[],
    permissionLevels: { id: string, name: string }[]
}) {
    const { data, setData, post, processing, errors } = useForm<{
        name: string,
        username: string,
        email: string,
        password: string,
        password_confirmation: string,
        teamSelections: number[],
        teamPermissions: { [teamId: number]: string },
        roleSelection: number | undefined
    }>({
        name: '',
        username: '',
        email: '',
        password: '',
        password_confirmation: '',
        teamSelections: [],
        teamPermissions: {},
        roleSelection: 4,
    })

    const getInitials = (name: string) => {
        return name
            .split(" ")
            .map(part => part[0])
            .join("")
            .toUpperCase()
    }

    const handleSubmit = (e: { preventDefault: () => void }) => {
        e.preventDefault()
        post(route('user.create'), {
            preserveScroll: true,
            onSuccess: () => {
                toast.success('User created successfully', {
                    duration: 1500,
                    onAutoClose(toast) {
                        router.visit(window.location.href, {
                            preserveScroll: true
                        });
                    },
                })
            },
            onError: () => {
                console.log(errors)
            }
        })
    }

    const handleTeamSelection = (teamId: number, checked: string | boolean) => {
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
            <Head title="Create User" />
            <div className="container mx-auto py-6 max-w-3xl">
                <div className="flex items-center justify-between">
                    <Button asChild variant="ghost" className="mb-6 pl-0 flex items-center gap-2">
                        <Link href={route('dashboard.users')}>
                            <ArrowLeft className="h-4 w-4" />
                            Back to Users
                        </Link>
                    </Button>
                </div>

                <Card>
                    <CardHeader>
                        <div className="flex items-center gap-4">
                            <Avatar className="h-16 w-16 border-4 border-background">
                                <AvatarImage
                                    src={undefined}
                                    alt={'Create User'}
                                />
                                <AvatarFallback className="text-lg">{getInitials('Create User')}</AvatarFallback>
                            </Avatar>
                            <div>
                                <CardTitle className="text-2xl">Create User Profile</CardTitle>
                                <CardDescription className="text-base">Create new user information, teams, and roles</CardDescription>
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

                            <div className="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div className="space-y-2">
                                    <label htmlFor="password" className="text-sm font-medium">Password</label>
                                    <Input
                                        id="password"
                                        type="password"
                                        value={data.password}
                                        onChange={e => setData('password', e.target.value)}
                                        placeholder="Enter password"
                                    />
                                    {errors.password && <p className="text-sm text-red-600">{errors.password}</p>}
                                </div>

                                <div className="space-y-2">
                                    <label htmlFor="password_confirmation" className="text-sm font-medium">Confirm Password</label>
                                    <Input
                                        id="password_confirmation"
                                        type="password"
                                        value={data.password_confirmation}
                                        onChange={e => setData('password_confirmation', e.target.value)}
                                        placeholder="Confirm password"
                                    />
                                    {errors.password_confirmation && (
                                        <p className="text-sm text-red-600">{errors.password_confirmation}</p>
                                    )}
                                </div>
                            </div>

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
                                <Link href={route('dashboard.users')}>Cancel</Link>
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
