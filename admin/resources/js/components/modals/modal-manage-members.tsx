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
import { Member, MemberForm, PendingInvitationMember } from "@/types"
import { Plus, Trash2 } from "lucide-react"
import { ReactNode, useState } from "react"
import { Badge } from "../ui/badge"

export function ModalManageMembers({
    members,
    pendingInvitation,
    trigger,
    onAddMember,
    onRemoveMember,
    onUpdateRole,
    teamName,
}: {
    members: Member[]
    pendingInvitation: PendingInvitationMember[]
    trigger: ReactNode
    onAddMember: (member: MemberForm) => void
    onRemoveMember: (memberId: number) => void
    onUpdateRole: (memberId: number, role: string) => void
    teamName?: string
}) {
    const getInitials = useInitials()
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
                                        <SelectItem value="database-maintainer">Database Maintener</SelectItem>
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
                                    <p className="text-sm text-muted-foreground">{member.email}</p>
                                </div>
                            </div>
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
                                        <SelectItem value="database-maintainer">Database Maintener</SelectItem>
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
                        </div>
                    ))}
                    {pendingInvitation.map((member) => (
                        <div key={member.id} className="flex items-center justify-between p-2 rounded-md bg-muted/50">
                            <div className="flex items-center gap-2">
                                <Avatar className="flex items-center justify-center text-primary-foreground bg-primary">
                                    <span className="text-xs">{getInitials(member.name)}</span>
                                </Avatar>
                                <div>
                                    <p className="font-medium">{member.name}</p>
                                    <p className="text-sm text-muted-foreground">{member.email}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <Badge variant="outline">Pending Invitation</Badge>
                                <Badge variant="outline">Expires {member.expires_at}</Badge>
                                <Select
                                    value={member.permission_level}
                                    onValueChange={(value) => onUpdateRole(member.id, value)}
                                    disabled={true}
                                >
                                    <SelectTrigger className="w-[200px]">
                                        <SelectValue />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="super-admin">Super Admin</SelectItem>
                                        <SelectItem value="team-manager">Team Manager</SelectItem>
                                        <SelectItem value="member">Member</SelectItem>
                                        <SelectItem value="database-maintainer">Database Maintener</SelectItem>
                                    </SelectContent>
                                </Select>
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
