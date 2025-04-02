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
import { Member } from "@/types"
import { Plus, Trash2 } from "lucide-react"
import { ReactNode, useState } from "react"

export function ModalManageMembers({
    members,
    trigger,
    onAddMember,
    onRemoveMember,
    onUpdateRole,
}: {
    members: Member[]
    trigger: ReactNode
    onAddMember: (member: Omit<Member, "id">) => void
    onRemoveMember: (memberId: number) => void
    onUpdateRole: (memberId: number, role: string) => void
}) {
    const getInitials = useInitials()
    const [isOpen, setIsOpen] = useState(false)
    const [newMember, setNewMember] = useState({ name: "", email: "", role: "Dev" })

    const handleAddMember = () => {
        onAddMember(newMember)
        setNewMember({ name: "", email: "", role: "Member" })
    }

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-1/2">
                <DialogHeader>
                    <DialogTitle>Manage Team Members</DialogTitle>
                    <DialogDescription>Add or remove team members</DialogDescription>
                </DialogHeader>

                {/* Add Member Form */}
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

                {/* Members List */}
                <div className="border-t pt-4 space-y-3">
                    {members.map((member) => (
                        <div key={member.id} className="flex items-center justify-between p-2 rounded-md bg-muted/50">
                            <div className="flex items-center gap-2">
                                <Avatar className="flex items-center justify-center text-primary-foreground bg-primary">
                                    <span className="text-xs">{getInitials(member.name)}</span>
                                </Avatar>
                                <div>
                                    <p className="font-medium">{member.name} {member.role}</p>
                                    <p className="text-sm text-muted-foreground">{member.email}</p>
                                </div>
                            </div>
                            <div className="flex items-center gap-2">
                                <Select
                                    value={member.role}
                                    onValueChange={(value) => onUpdateRole(member.id, value)}
                                    disabled={member.role === "Super Admin"}
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
                                {member.role !== "Super Admin" && (
                                    <Button
                                        variant="ghost"
                                        size="icon"
                                        onClick={() => onRemoveMember(member.id)}
                                    >
                                        <Trash2 className="h-4 w-4" />
                                    </Button>
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
