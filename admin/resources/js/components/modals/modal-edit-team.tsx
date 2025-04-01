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
import { Textarea } from "@/components/ui/textarea"
import { ReactNode, useState } from "react"

type TeamForm = {
    name: string
    description: string
}

export function ModalEditTeam({
    trigger,
    onSave,
    initValues = { name: "", description: "" },
}: {
    trigger: ReactNode
    onSave: (team: TeamForm) => void
    initValues?: TeamForm
}) {
    const [isOpen, setIsOpen] = useState(false)
    const [form, setForm] = useState<TeamForm>(initValues)

    const handleSubmit = () => {
        if (!form.name.trim()) return
        onSave(form)
        setIsOpen(false)
        setForm(initValues)
    }

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            <DialogTrigger asChild>{trigger}</DialogTrigger>
            <DialogContent className="sm:max-w-[425px]">
                <DialogHeader>
                    <DialogTitle>Create New Team</DialogTitle>
                    <DialogDescription>
                        Create a new team to collaborate with other users.
                    </DialogDescription>
                </DialogHeader>

                <div className="grid gap-4 py-4">
                    <div className="grid grid-cols-4 items-center gap-4">
                        <Label htmlFor="name" className="text-right">Name</Label>
                        <Input
                            id="name"
                            value={form.name}
                            onChange={(e) => setForm({ ...form, name: e.target.value })}
                            className="col-span-3"
                            placeholder="Team name"
                        />
                    </div>
                    <div className="grid grid-cols-4 items-center gap-4">
                        <Label htmlFor="description" className="text-right">Description</Label>
                        <Textarea
                            id="description"
                            value={form.description}
                            onChange={(e) => setForm({ ...form, description: e.target.value })}
                            className="col-span-3"
                            placeholder="Team description"
                        />
                    </div>
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => setIsOpen(false)}>Cancel</Button>
                    <Button onClick={handleSubmit} disabled={!form.name.trim()}>Create</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    )
}
