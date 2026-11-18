import { useInitials } from "@/hooks/use-initials";
import { usePermission } from "@/lib/auth";
import { useTeamActions } from "@/hooks/use-team-actions";
import { SharedData, TeamCardProps } from "@/types";
import { usePage } from "@inertiajs/react";
import { Activity, CheckCircle, CirclePlusIcon, FolderClosed, MoreHorizontal, RefreshCcw, Users } from "lucide-react";
import { AppTooltip } from "../app-tooltip";
import { ModalCreateDatabase } from "../modals/modal-create-database";
import { ModalCreateGroupOnly } from "../modals/modal-create-group-only";
import { ModalEditTeam } from "../modals/modal-edit-team";
import { ModalManageMembers } from "../modals/modal-manage-members";
import { Avatar } from "../ui/avatar";
import { Badge } from "../ui/badge";
import { Button } from "../ui/button";
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "../ui/card";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from "../ui/dropdown-menu";
import { ScrollArea } from "../ui/scroll-area";
import { Tabs, TabsContent, TabsList, TabsTrigger } from "../ui/tabs";
import { GroupTree } from "./group-tree"

export default function TeamCard({ team, isCurrent, totalTeams: totalTeams, isArchived = false }: TeamCardProps) {
    const getInitials = useInitials();
    const { can } = usePermission();
    const { databases } = usePage<SharedData>().props;

    const {
        groups,
        handleCreateGroup,
        handleDatabaseSubmit,
        handleRestoreTeam,
        handleEditTeamOnSave,
        handleAddMember,
        handleRemoveMember,
        handleUpdateRole,
        handleCreateGroupSubmit,
        handleDeleteTeam,
        handleResendInvitation,
        handleRevokeInvitation,
    } = useTeamActions({ team });

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
                            <AppTooltip text="Team members">
                                <Badge variant="outline" className="ml-2">
                                    <Users className="h-3 w-3 mr-1" />
                                    {team.members}
                                </Badge>
                            </AppTooltip>
                            {team.pending_invitations.length > 0 && (can('manage-teams') || can('manage-team-members')) && (
                                <AppTooltip text='Pending invitation'>
                                    <Badge variant="outline" className="ml-2 outline-yellow-500 dark:outline-white bg-yellow-500 text-white dark:bg-transparent dark:text-yellow-500">
                                        <Users className="h-3 w-3 mr-1" />
                                        {team.pending_invitations.length}
                                    </Badge>
                                </AppTooltip>
                            )}
                        </CardTitle>
                        <CardDescription>{team.description}</CardDescription>
                    </div>
                    {(can('manage-teams') || can('manage-team-members') || can('view-team-members')) && (
                        <div className="flex gap-2">
                            {can('create-databases') && !isArchived && (
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
                            )}
                            {isArchived ? (
                                <AppTooltip text="Restore team">
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        className="h-8 w-8"
                                        onClick={() => handleRestoreTeam(team.id)}
                                    >
                                        <RefreshCcw className="h-4 w-4" />
                                        <span className="sr-only">Restore team</span>
                                    </Button>
                                </AppTooltip>
                            ) : (
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
                                        {can('update-teams') && (
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
                                        )}
                                        {(can('view-team-members') || can('manage-team-members')) && (
                                            <DropdownMenuItem asChild>
                                                <ModalManageMembers
                                                    teamName={team.name}
                                                    trigger={
                                                        <Button variant="ghost" size={"sm"} className="flex w-full justify-start">
                                                            Manage members
                                                        </Button>
                                                    }
                                                    members={team.team_members}
                                                    pendingInvitation={team.pending_invitations}
                                                    onResendInvitation={(invitationId) => handleResendInvitation(invitationId)}
                                                    onRevokeInvitation={(invitationId) => handleRevokeInvitation(invitationId)}
                                                    onAddMember={(member) => handleAddMember(member)}
                                                    onRemoveMember={(memberId) => handleRemoveMember(memberId, team.id)}
                                                    onUpdateRole={(memberId, role) => handleUpdateRole(memberId, role)}
                                                />
                                            </DropdownMenuItem>
                                        )}
                                        {can('create-groups') && (
                                            <DropdownMenuItem asChild>
                                                <ModalCreateGroupOnly
                                                    trigger={
                                                        <Button variant="ghost" size={"sm"} className="flex w-full justify-start">
                                                            Add group
                                                        </Button>
                                                    }
                                                    onSave={(group) => {
                                                        group.teamId = team.id;
                                                        handleCreateGroupSubmit(group)
                                                    }}
                                                />
                                            </DropdownMenuItem>
                                        )}
                                        {can('delete-teams') && (
                                            <>
                                                <DropdownMenuSeparator />
                                                <DropdownMenuItem asChild>
                                                    <Button
                                                        variant="destructive"
                                                        size={"sm"}
                                                        className="flex w-full justify-start"
                                                        disabled={totalTeams === 1}
                                                        onClick={() => handleDeleteTeam(team.id)}
                                                    >
                                                        Archive team
                                                    </Button>
                                                </DropdownMenuItem>
                                            </>
                                        )}
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            )}
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
                            <GroupTree groups={team.groups} team={team} isArchived={isArchived} />
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
