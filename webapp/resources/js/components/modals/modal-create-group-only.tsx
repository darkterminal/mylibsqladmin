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
import { ReactNode, useState } from "react"

export interface CreateGroupOnlyForm {
    groupName: string
    teamId: number | undefined
}

export function ModalCreateGroupOnly({
    trigger,
    onSave,
}: {
    trigger: ReactNode
    onSave: (groupForm: CreateGroupOnlyForm) => void
}) {
    const [isOpen, setIsOpen] = useState(false)
    const [groupForm, setGroupForm] = useState<CreateGroupOnlyForm>({
        groupName: "",
        teamId: undefined
    })

    const handleSubmit = () => {
        if (!groupForm.groupName.trim()) return
        onSave(groupForm)
        setIsOpen(false)
        setGroupForm({ groupName: "", teamId: undefined })
    }

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Create New Group</DialogTitle>
                    <DialogDescription>Organize databases into groups</DialogDescription>
                </DialogHeader>

                <div className="grid gap-4 py-4">
                    <div className="grid grid-cols-4 items-center gap-4">
                        <Label htmlFor="name" className="text-right">Name</Label>
                        <Input
                            id="name"
                            value={groupForm.groupName}
                            onChange={(e) => setGroupForm({ ...groupForm, groupName: e.target.value })}
                            className="col-span-3"
                            placeholder="Group name"
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => setIsOpen(false)}>Cancel</Button>
                    <Button onClick={handleSubmit} disabled={!groupForm.groupName.trim()}>Create</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    )
}
