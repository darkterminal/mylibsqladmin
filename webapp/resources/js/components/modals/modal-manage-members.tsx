import { Avatar } from "@/components/ui/avatar"
import { Button } from "@/components/ui/button"
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { useInitials } from "@/hooks/use-initials"
import { usePermission } from "@/lib/auth"
import { Member, MemberForm, PendingInvitationMember, SharedData } from "@/types"
import { usePage } from "@inertiajs/react"
import { Plus, Send, Trash2 } from "lucide-react"
import { ReactNode, useState } from "react"
import { Badge } from "../ui/badge"

export function ModalManageMembers({
    members,
    pendingInvitation,
    trigger,
    onAddMember,
    onRemoveMember,
    onUpdateRole,
    onResendInvitation,
    onRevokeInvitation,
    teamName,
}: {
    members: Member[]
    pendingInvitation: PendingInvitationMember[]
    trigger: ReactNode
    onAddMember: (member: MemberForm) => void
    onRemoveMember: (memberId: number) => void
    onUpdateRole: (memberId: number, role: string) => void
    onResendInvitation?: (invitationId: number) => void
    onRevokeInvitation?: (invitationId: number) => void
    teamName?: string
}) {
    const getInitials = useInitials()
    const { can } = usePermission()
    const { auth } = usePage<SharedData>().props
    const [isOpen, setIsOpen] = useState(false)
    const [newMember, setNewMember] = useState<MemberForm>({
        name: "",
        email: "",
        role: "member"
    });

    const handleAddMember = () => {
        onAddMember(newMember)
        setNewMember({ name: "", email: "", role: "member" })
    }

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-1/2">
                <DialogHeader>
                    <DialogTitle>Manage {teamName ? `(${teamName})` : ""} Team Members</DialogTitle>
                    <DialogDescription>Add or remove team members</DialogDescription>
                </DialogHeader>

                {/* Add Member Form */}
                {can('create-team-members') && (
                    <form>
                        <div className="grid gap-4">
                            <div className="grid grid-cols-12 gap-2">
                                <div className="col-span-4">
                                    <Label>Name</Label>
                                    <Input
                                        value={newMember.name}
                                        onChange={(e) => setNewMember({ ...newMember, name: e.target.value })}
                                        placeholder="John Doe"
                                    />
                                </div>
                                <div className="col-span-4">
                                    <Label>Email</Label>
                                    <Input
                                        type="email"
                                        value={newMember.email}
                                        onChange={(e) => setNewMember({ ...newMember, email: e.target.value })}
                                        placeholder="john@example.com"
                                    />
                                </div>
                                <div className="col-span-4">
                                    <Label>Role</Label>
                                    <Select
                                        value={newMember.role}
                                        onValueChange={(value) => setNewMember({ ...newMember, role: value })}
                                    >
                                        <SelectTrigger>
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="super-admin">Super Admin</SelectItem>
                                            <SelectItem value="team-manager">Team Manager</SelectItem>
                                            <SelectItem value="member">Member</SelectItem>
                                            <SelectItem value="database-maintainer">Database Maintainer</SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                            </div>
                            <Button
                                onClick={handleAddMember}
                                disabled={!newMember.name.trim() || !newMember.email.trim()}
                            >
                                <Plus className="mr-2 h-4 w-4" /> Add Member
                            </Button>
                        </div>
                    </form>
                )}

                {/* Members List */}
                <div className="border-t pt-4 space-y-3">
                    {members.map((member) => (
                        <div key={member.id} className="flex items-center justify-between p-2 rounded-md bg-muted/50">
                            <div className="flex items-center gap-2">
                                <Avatar className="flex items-center justify-center text-primary-foreground bg-primary">
                                    <span className="text-xs">{getInitials(member.name)}</span>
                                </Avatar>
                                <div>
                                    <p className="font-medium">{member.name}</p>
                                    {can('manage-team-members') && <p className="text-xs text-muted-foreground">{member.email}</p>}
                                </div>
                            </div>
                            {can('update-team-members') ? (
                                <div className="flex items-center gap-2">
                                    <Select
                                        value={member.role}
                                        onValueChange={(value) => onUpdateRole(member.id, value)}
                                        disabled={member.role === "super-admin"}
                                    >
                                        <SelectTrigger className="w-[200px]">
                                            <SelectValue />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="super-admin">Super Admin</SelectItem>
                                            <SelectItem value="team-manager">Team Manager</SelectItem>
                                            <SelectItem value="member">Member</SelectItem>
                                            <SelectItem value="database-maintainer">Database Maintainer</SelectItem>
                                        </SelectContent>
                                    </Select>
                                    {member.role !== "super-admin" && (
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            onClick={() => onRemoveMember(member.id)}
                                            disabled={member.role === "super-admin"}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    )}
                                </div>
                            ) : (
                                <div className="space-x-1">
                                    <Badge variant="outline">{member.role}</Badge>
                                    {auth.user.id === member.id && (
                                        <Badge variant="outline">You</Badge>
                                    )}
                                </div>
                            )}
                        </div>
                    ))}
                    {pendingInvitation.map((member) => (
                        <div key={member.id} className="flex flex-wrap items-center justify-between gap-2 rounded-md bg-muted/50 p-2">
                            <div className="flex min-w-0 items-center gap-2">
                                <Avatar className="flex items-center justify-center text-primary-foreground bg-primary">
                                    <span className="text-xs">{getInitials(member.name)}</span>
                                </Avatar>
                                <div className="min-w-0">
                                    <p className="truncate font-medium" title={member.name}>{member.name}</p>
                                    <p className="break-all text-xs text-muted-foreground">{member.email}</p>
                                    <p className="text-xs text-muted-foreground">
                                        Invited by {member.inviter} · Invited {member.sent_at}
                                    </p>
                                    <p className="text-xs text-muted-foreground">Expires {member.expires_at} · {member.permission_level}</p>
                                </div>
                            </div>
                            <div className="flex shrink-0 flex-wrap items-center justify-end gap-1.5">
                                <Badge variant="outline">Pending</Badge>
                                {can('manage-team-members') && (onResendInvitation || onRevokeInvitation) && (
                                    <div className="flex items-center gap-1.5">
                                        {onResendInvitation && (
                                            <Button
                                                variant="outline"
                                                size="icon"
                                                className="h-7 w-7"
                                                aria-label="Resend invitation"
                                                title="Resend invitation"
                                                onClick={() => onResendInvitation(member.id)}
                                            >
                                                <Send className="h-3.5 w-3.5" />
                                            </Button>
                                        )}
                                        {onRevokeInvitation && (
                                            <Button
                                                variant="ghost"
                                                size="icon"
                                                className="h-7 w-7 text-muted-foreground hover:text-destructive"
                                                aria-label="Revoke invitation"
                                                title="Revoke invitation"
                                                onClick={() => onRevokeInvitation(member.id)}
                                            >
                                                <Trash2 className="h-3.5 w-3.5" />
                                            </Button>
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>
                    ))}
                </div>

                <DialogFooter>
                    <Button onClick={() => setIsOpen(false)}>Close</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    )
}
