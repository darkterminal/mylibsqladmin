import { useInitials } from "@/hooks/use-initials";
import { apiFetch } from "@/lib/api";
import { usePermission } from "@/lib/auth";
import { MemberForm, SharedData, Team, TeamCardProps, TeamForm } from "@/types";
import { router, usePage } from "@inertiajs/react";
import { CheckCircle, Database, File, FolderClosed, GitBranch, MoreHorizontal, RefreshCcw } from "lucide-react";
import { useEffect, useState } from "react";
import { toast } from "sonner";
import { AppTooltip } from "../app-tooltip";
import ButtonOpenDatabaseStudio from "../button-actions/action-open-database-studio";
import { CreateDatabaseProps } from "../modals/modal-create-database";
import { CreateGroupOnlyForm, ModalCreateGroupOnly } from "../modals/modal-create-group-only";
import { ModalEditTeam } from "../modals/modal-edit-team";
import { ModalManageMembers } from "../modals/modal-manage-members";
import { Avatar } from "../ui/avatar";
import { Badge } from "../ui/badge";
import { Button } from "../ui/button";
import { ComboboxOption } from "../ui/combobox";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from "../ui/dropdown-menu";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "../ui/table";

export default function TeamsTable({ teams, isArchived = false }: { teams: Team[], isArchived?: boolean }) {
    const [currentTeamId, setCurrentTeamId] = useState<string | null>(null)

    useEffect(() => {
        setCurrentTeamId(localStorage.getItem('currentTeamId'))
    }, [])

    return (
        <div className="rounded-md border">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Team</TableHead>
                        <TableHead>Members</TableHead>
                        <TableHead>Groups</TableHead>
                        <TableHead>Databases</TableHead>
                        <TableHead>Recent Activity</TableHead>
                        <TableHead className="w-[50px]">Actions</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {teams.map((team) => (
                        <TeamTableRow
                            key={team.id}
                            team={team}
                            isCurrent={String(team.id) === currentTeamId}
                            totalTeams={teams.length}
                            isArchived={isArchived}
                        />
                    ))}
                </TableBody>
            </Table>
        </div>
    )
}

function TeamTableRow({ team, isCurrent, totalTeams: totalTeams, isArchived = false }: TeamCardProps) {
    const getInitials = useInitials();
    const { can } = usePermission();

    const { csrfToken, databases, groups: databaseGroups } = usePage<SharedData>().props;
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
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
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
            groupId: Number(formData.groupId),
            teamId: Number(teamId),
        };

        const response = await apiFetch(route('database.create'), {
            method: 'POST',
            body: JSON.stringify(submittedData),
            headers: {
                'X-CSRF-TOKEN': csrfToken
            }
        });

        if (!response.ok) {
            toast.error('Failed to create database');
        }

        const refreshSession = await apiFetch(route('api.teams.databases', Number(teamId)), {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': csrfToken
            }
        });

        if (!refreshSession.ok) {
            toast.error('Failed to refresh session');
        }

        router.visit(window.location.href, {
            preserveScroll: true
        });
    }

    const handleEditTeamOnSave = async (formData: TeamForm) => {
        const response = await apiFetch(route('team.update', team.id), {
            method: 'PUT',
            body: JSON.stringify(formData),
            headers: {
                'X-CSRF-TOKEN': csrfToken
            }
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
            headers: {
                'X-CSRF-TOKEN': csrfToken
            }
        });

        if (response.ok) {
            router.visit(window.location.href, {
                preserveScroll: true,
            });
        }
    }

    const handleCreateGroupSubmit = async (groupOnlyForm: CreateGroupOnlyForm) => {
        try {
            const response = await apiFetch(route('api.group.create-only'), {
                method: 'POST',
                body: JSON.stringify({ name: groupOnlyForm.groupName, team_id: Number(groupOnlyForm.teamId) }),
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            });

            // Parse response regardless of status
            const responseData = await response.json();

            if (!response.ok) {
                // Handle validation errors (422)
                if (response.status === 422) {
                    throw {
                        message: 'Validation failed',
                        errors: responseData.errors,
                        status: response.status
                    };
                }

                // Handle other errors
                throw {
                    message: responseData.message || 'Request failed',
                    status: response.status
                };
            }

            // Handle success
            const refreshSession = await apiFetch(route('api.teams.databases', Number(groupOnlyForm.teamId)), {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            });

            if (!refreshSession.ok) {
                throw new Error('Failed to refresh session');
            }

            router.visit(window.location.href, {
                preserveScroll: true,
            });

            return responseData;

        } catch (error: { message: string, status: number } | any) {
            console.error("Error creating group:", error);
            toast.error('Failed to create group: ' + error.message);

            // Throw structured error for UI handling
            if (error instanceof Error) {
                throw {
                    message: error.message,
                    status: 500
                };
            }

            // Preserve backend error structure
            throw error;
        }
    };

    const handleUpdateRole = async (userId: number, role: string) => {
        router.put(route('teams.members.update-role', {
            team: team.id,
            user: userId
        }), { role }, {
            onSuccess: () => {
                router.visit(window.location.href, {
                    preserveScroll: true,
                });
            }
        });
    }

    const handleDeleteTeam = async (teamId: number) => {
        const response = await apiFetch(route('team.delete', teamId), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken
            }
        });

        if (!response.ok) {
            toast.error('Failed to delete team');
            return;
        }

        const data = await response.json();

        toast.success(data.message, {
            duration: 2500,
            position: 'top-center',
            onAutoClose: () => {
                router.visit(window.location.href, {
                    preserveScroll: true,
                });
            },
            onDismiss: () => {
                router.visit(window.location.href, {
                    preserveScroll: true,
                });
            }
        });
    }

    const handleRemoveMember = async (userId: number, teamId: number) => {
        const response = await apiFetch(route('teams.members.delete', {
            team: teamId,
            user: userId
        }), {
            method: 'DELETE',
            headers: {
                'X-CSRF-TOKEN': csrfToken
            }
        })

        if (response.ok) {
            router.visit(window.location.href, {
                preserveScroll: true,
            });
        }

        toast.error('Failed to remove member');
    }

    const handleRestoreTeam = async (teamId: number) => {
        router.put(route('team.restore', teamId), {
            preserveScroll: true,
        }, {
            onSuccess: (page) => {
                if (page.props.flash.success) {
                    toast.success(page.props.flash.success, {
                        duration: 2500,
                        position: 'top-center',
                        onAutoClose: () => {
                            router.visit(route('dashboard.teams'), {
                                preserveScroll: true,
                            });
                        },
                        onDismiss: () => {
                            router.visit(route('dashboard.teams'), {
                                preserveScroll: true,
                            });
                        }
                    });
                } else {
                    toast.error(page.props.flash.error, {
                        duration: 2500,
                        position: 'top-center',
                        onAutoClose: () => {
                            router.visit(window.location.href, {
                                preserveScroll: true
                            })
                        },
                        onDismiss: () => {
                            router.visit(window.location.href, {
                                preserveScroll: true
                            })
                        }
                    })
                }
            }
        });
    }

    // get all database inside team.groups
    const allDatabases = team.groups.map((group) => group.databases).flat();

    return (
        <>
            <TableRow>
                <TableCell>
                    <div className="space-y-1">
                        <div className="font-medium flex gap-2">{team.name} {isCurrent && <CheckCircle className="h-4 w-4 text-green-400" />}</div>
                        <div className="text-sm text-muted-foreground">{team.description}</div>
                    </div>
                </TableCell>
                <TableCell>
                    <div className="flex items-center gap-2">
                        <div className="flex -space-x-2">
                            {team.team_members.slice(0, 3).map((member) => (
                                <Avatar key={member.id} className="flex items-center justify-center text-primary-foreground bg-primary h-6 w-6">
                                    <span className="text-xs">{getInitials(member.name)}</span>
                                </Avatar>
                            ))}
                            {team.members > 3 && (
                                <div className="h-6 w-6 rounded-full bg-muted border-2 border-background flex items-center justify-center text-xs">
                                    +{team.members - 3}
                                </div>
                            )}
                        </div>
                        <span className="text-sm text-muted-foreground">{team.members}</span>
                    </div>
                </TableCell>
                <TableCell>
                    <div className="space-y-1">
                        {team.groups.length > 0 ? (
                            team.groups.map((group) => (
                                <DropdownMenu key={group.id}>
                                    <DropdownMenuTrigger asChild>
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            className="h-auto p-1 justify-start hover:bg-muted/50 transition-colors"
                                        >
                                            <div className="flex items-center gap-1 text-sm">
                                                <FolderClosed className="h-3 w-3 text-muted-foreground" />
                                                <span>{group.name}</span>
                                                <Badge variant="outline" className="text-xs">
                                                    {group.databases.length}
                                                </Badge>
                                            </div>
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent className="w-80" align="start">
                                        <DropdownMenuLabel className="flex items-center gap-2">
                                            <FolderClosed className="h-4 w-4 text-muted-foreground" />
                                            [{group.name}] - Group Databases
                                        </DropdownMenuLabel>
                                        <DropdownMenuSeparator />
                                        {team.groups.filter((g) => g.databases.length > 0 && g.name === group.name).length ? (
                                            <div className="max-h-[200px] overflow-auto">
                                                {team.groups.filter((g) => g.databases.length > 0 && g.name === group.name)[0].databases.map((db) => (
                                                    <DropdownMenuItem key={db.id} className="flex items-center justify-between p-3">
                                                        <div className="flex items-center gap-2">
                                                            {(() => {
                                                                switch (db.type) {
                                                                    case "standalone":
                                                                        return <Database className="h-3.5 w-3.5 text-primary" />;
                                                                    case "schema":
                                                                        return <File className="h-3.5 w-3.5 text-primary" />;
                                                                    default:
                                                                        return <GitBranch className="h-3.5 w-3.5 text-primary" />;
                                                                }
                                                            })()}
                                                            <span className="text-sm">{db.name}</span>
                                                        </div>
                                                        <div className="flex items-center gap-2">
                                                            <Badge
                                                                variant={
                                                                    db.type === "standalone"
                                                                        ? "default"
                                                                        : db.type === "schema database"
                                                                            ? "secondary"
                                                                            : "outline"
                                                                }
                                                                className="text-xs"
                                                            >
                                                                {db.type}
                                                            </Badge>
                                                            {!isArchived && <ButtonOpenDatabaseStudio databaseName={db.name} team={team} />}
                                                        </div>
                                                    </DropdownMenuItem>
                                                ))}
                                            </div>
                                        ) : (
                                            <DropdownMenuItem disabled>
                                                <span className="text-sm text-muted-foreground">No databases in this group</span>
                                            </DropdownMenuItem>
                                        )}
                                    </DropdownMenuContent>
                                </DropdownMenu>
                            ))
                        ) : (
                            <span className="text-sm text-muted-foreground">No groups</span>
                        )}
                    </div>
                </TableCell>
                <TableCell>
                    <DropdownMenu>
                        <DropdownMenuTrigger asChild>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="h-auto p-1 justify-start hover:bg-muted/50 transition-colors"
                            >
                                <div className="flex items-center gap-2">
                                    <Database className="h-4 w-4 text-primary" />
                                    <span className="text-sm">{allDatabases.length}</span>
                                </div>
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent className="w-80" align="start">
                            <DropdownMenuLabel className="flex items-center gap-2">
                                <Database className="h-4 w-4 text-primary" />
                                All Databases ({databases.length})
                            </DropdownMenuLabel>
                            <DropdownMenuSeparator />
                            {allDatabases.length > 0 ? (
                                <div className="max-h-[200px] overflow-auto">
                                    {allDatabases.map((db) => (
                                        <DropdownMenuItem key={db.id} className="flex items-center justify-between p-3">
                                            <div className="flex items-center gap-2">
                                                {(() => {
                                                    switch (db.type) {
                                                        case "standalone":
                                                            return <Database className="h-3.5 w-3.5 text-primary" />;
                                                        case "schema":
                                                            return <File className="h-3.5 w-3.5 text-primary" />;
                                                        default:
                                                            return <GitBranch className="h-3.5 w-3.5 text-primary" />;
                                                    }
                                                })()}
                                                <div>
                                                    <div className="text-sm font-medium">{db.name}</div>
                                                </div>
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
                                                {!isArchived && <ButtonOpenDatabaseStudio databaseName={db.name} team={team} />}
                                            </div>
                                        </DropdownMenuItem>
                                    ))}
                                </div>
                            ) : (
                                <DropdownMenuItem disabled>
                                    <span className="text-sm text-muted-foreground">No databases found</span>
                                </DropdownMenuItem>
                            )}
                        </DropdownMenuContent>
                    </DropdownMenu>
                </TableCell>
                <TableCell>
                    <div className="space-y-1">
                        {team.recentActivity.length > 0 ? (
                            <div className="text-sm">
                                <div className="font-medium">{team.recentActivity[0].user}</div>
                                <div className="text-muted-foreground">
                                    {team.recentActivity[0].action} • {team.recentActivity[0].time}
                                </div>
                            </div>
                        ) : (
                            <span className="text-sm text-muted-foreground">No recent activity</span>
                        )}
                    </div>
                </TableCell>
                <TableCell>
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
                                                Delete team
                                            </Button>
                                        </DropdownMenuItem>
                                    </>
                                )}
                            </DropdownMenuContent>
                        </DropdownMenu>
                    )}
                </TableCell>
            </TableRow>
        </>
    )
}
