import { useInitials } from "@/hooks/use-initials";
import { usePermission } from "@/lib/auth";
import { useTeamActions } from "@/hooks/use-team-actions";
import { type Team, type TeamCardProps } from "@/types";
import { AppTooltip } from "../app-tooltip";
import ButtonOpenDatabaseStudio from "../button-actions/action-open-database-studio";
import { ModalEditTeam } from "../modals/modal-edit-team";
import { ModalManageMembers } from "../modals/modal-manage-members";
import { ModalCreateGroupOnly } from "../modals/modal-create-group-only";
import { Avatar } from "../ui/avatar";
import { Badge } from "../ui/badge";
import { Button } from "../ui/button";
import { DropdownMenu, DropdownMenuContent, DropdownMenuItem, DropdownMenuLabel, DropdownMenuSeparator, DropdownMenuTrigger } from "../ui/dropdown-menu";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "../ui/table";
import { useCurrentTeamId } from "@/lib/current-team";
import { CheckCircle, Database, File, FolderClosed, GitBranch, MoreHorizontal, RefreshCcw } from "lucide-react";

export default function TeamsTable({ teams, isArchived = false }: { teams: Team[], isArchived?: boolean }) {
    const currentTeamId = useCurrentTeamId()


    return (
        <div className="overflow-x-auto rounded-md border">
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
                    {teams.length === 0 && (
                        <TableRow>
                            <TableCell colSpan={6} className="h-24 text-center text-muted-foreground">
                                No teams found.
                            </TableCell>
                        </TableRow>
                    )}
                    {teams.map((team) => (
                        <TeamTableRow
                            key={team.id}
                            team={team}
                            isCurrent={team.id === currentTeamId}
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

    const {
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
                                All Databases ({allDatabases.length})
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
                            <div className="text-sm leading-snug">
                                <div className="font-medium">{team.recentActivity[0].user}</div>
                                <div className="text-muted-foreground">
                                    {team.recentActivity[0].action}
                                    {team.recentActivity[0].database ? (
                                        <> on <span className="font-medium text-foreground">{team.recentActivity[0].database}</span></>
                                    ) : null}
                                </div>
                                <div className="text-xs text-muted-foreground">{team.recentActivity[0].time}</div>
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
                </TableCell>
            </TableRow>
        </>
    )
}
