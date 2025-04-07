import { useInitials } from "@/hooks/use-initials";
import { apiFetch } from "@/lib/api";
import { usePermission } from "@/lib/auth";
import { MemberForm, SharedData, TeamCardProps, TeamForm } from "@/types";
import { router, usePage } from "@inertiajs/react";
import { Activity, CheckCircle, CirclePlusIcon, FolderClosed, MoreHorizontal, Users } from "lucide-react";
import { useState } from "react";
import { AppTooltip } from "../app-tooltip";
import { CreateDatabaseProps, ModalCreateDatabase } from "../modals/modal-create-database";
import { ModalCreateGroupOnly } from "../modals/modal-create-group-only";
import { ModalEditTeam } from "../modals/modal-edit-team";
import { ModalManageMembers } from "../modals/modal-manage-members";
import { Avatar } from "../ui/avatar";
import { Badge } from "../ui/badge";
import { Button } from "../ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "../ui/card";
import { ComboboxOption } from "../ui/combobox";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from "../ui/dropdown-menu";
import { ScrollArea } from "../ui/scroll-area";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "../ui/tabs";
import { GroupTree } from "./group-tree";

export default function TeamCard({ team, isCurrent }: TeamCardProps) {
    const getInitials = useInitials();
    const { can } = usePermission();

    const { databases, groups: databaseGroups } = usePage<SharedData>().props;
    const groupedDatabases = (databaseGroups || [])
        .map?.(group => ({
            label: group.name,
            value: group.id.toString()
        }))
        ?.sort((a, b) => Number(b.value) - Number(a.value)) || [];
    const [groups, setGroups] = useState<ComboboxOption[]>(groupedDatabases);

    const handleCreateGroup = async (name: string): Promise<string> => {
        try {
            const teamId = localStorage.getItem('currentTeamId');
            const response = await apiFetch(route('api.group.create-only'), {
                method: 'POST',
                body: JSON.stringify({ name, team_id: teamId }),
            });

            if (!response.ok) {
                throw new Error('Failed to create group');
            }

            const data = await response.json();
            const newGroupId = data.group.id as string;
            const newGroup: ComboboxOption = { value: newGroupId, label: name };
            setGroups((prev) => [...prev, newGroup].sort((a, b) => Number(b.value) - Number(a.value)));
            return newGroupId;
        } catch (error) {
            console.error("Error creating group:", error);
            throw error;
        }
    }

    const handleDatabaseSubmit = async (formData: CreateDatabaseProps) => {
        const teamId = formData.teamId || localStorage.getItem('currentTeamId');
        const submittedData = {
            database: formData.useExisting ? formData.childDatabase : formData.database,
            isSchema: formData.useExisting ? formData.database : formData.isSchema,
            groupId: Number(formData.groupName),
            teamId: Number(teamId),
        };

        router.post(route('database.create'), submittedData, {
            onSuccess: async () => await apiFetch(route('api.teams.databases', Number(teamId))),
            onFinish: () => router.visit(window.location.href)
        });
    }

    const handleEditTeamOnSave = async (formData: TeamForm) => {
        const response = await apiFetch(route('team.update', team.id), {
            method: 'PUT',
            body: JSON.stringify(formData),
        });
        if (response.ok) {
            router.visit(window.location.href, {
                preserveScroll: true,
            });
        }
    }

    const handleAddMember = async (member: MemberForm) => {
        const response = await apiFetch(route('teams.invitations.store', team.id), {
            method: 'POST',
            body: JSON.stringify(member),
        });

        if (response.ok) {
            router.visit(window.location.href, {
                preserveScroll: true,
            });
        }
    }

    const handleCreateGroupSubmit = async (name: string) => {
        try {
            const teamId = localStorage.getItem('currentTeamId');
            const response = await apiFetch(route('api.group.create-only'), {
                method: 'POST',
                body: JSON.stringify({ name, team_id: teamId }),
            });

            if (!response.ok) {
                throw new Error('Failed to create group');
            }

            const refreshSession = await apiFetch(route('api.teams.databases', Number(teamId)));

            if (!refreshSession.ok) {
                throw new Error('Failed to refresh session');
            }

            router.visit(window.location.href, {
                preserveScroll: true,
            });
        } catch (error) {
            console.error("Error creating group:", error);
            throw error;
        }
    }

    return (
        <Card className="h-full flex flex-col">
            <CardHeader className="pb-2">
                <div className="flex justify-between items-start">
                    <div className="space-y-1">
                        <CardTitle className="flex items-center">
                            {isCurrent && (
                                <CheckCircle className="h-4 w-4 mr-2 text-green-500" />
                            )}
                            {team.name}
                            <Badge variant="outline" className="ml-2">
                                <Users className="h-3 w-3 mr-1" />
                                {team.members}
                            </Badge>
                        </CardTitle>
                        <CardDescription>{team.description}</CardDescription>
                    </div>
                    {can('manage-teams') && (
                        <div className="flex gap-2">
                            <ModalCreateDatabase
                                existingDatabases={databases}
                                onSubmit={handleDatabaseSubmit}
                                groups={groups}
                                onCreateGroup={handleCreateGroup}
                                currentTeam={team}
                            >
                                <AppTooltip text='Create new database'>
                                    <Button variant={'outline'} size='icon' className="h-8 w-8">
                                        <CirclePlusIcon className='h-4 w-4' />
                                    </Button>
                                </AppTooltip>
                            </ModalCreateDatabase>
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
                                            onSave={(team) => handleEditTeamOnSave(team)}
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
                                            onAddMember={(member) => handleAddMember(member)}
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
                                            onSave={(groupName) => handleCreateGroupSubmit(groupName)}
                                        />
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem className="text-destructive">Delete team</DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    )}
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
                            <GroupTree groups={team.groups} team={team} />
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
        </Card>
    )
}
