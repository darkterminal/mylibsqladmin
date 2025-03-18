import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { DatabaseInGroupProps } from "@/types"
import { useForm } from "@inertiajs/react"
import { DialogDescription } from "@radix-ui/react-dialog"
import type React from "react"
import { useState } from "react"

export default function ModalCreateGroup({
    databases,
    children,
}: {
    databases: DatabaseInGroupProps[]
    children: React.ReactNode
}) {
    const [open, setOpen] = useState(false)
    const { data, setData, post, processing, errors, reset } = useForm({
        name: "",
        databases: [] as string[],
    })

    const handleNameChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const value = e.target.value.replace(/[^a-zA-Z0-9-_]/g, "")
        setData('name', value)
    }

    const handleSelectChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const selectedOptions = Array.from(e.target.selectedOptions).map((option) => option.value)
        setData('databases', selectedOptions)
    }

    const submit = (e: React.FormEvent) => {
        e.preventDefault()
        post(route('group.create'), {
            preserveScroll: true,
            onSuccess: () => {
                reset()
                setOpen(false)
            },
        })
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild disabled={databases.length === 0}>{children}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create New Group</DialogTitle>
                    <DialogDescription className="text-sm">
                        Create a new group by providing a name and selecting databases.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4" autoComplete="off">
                    <div className="space-y-2">
                        <Label htmlFor="name">Group Name</Label>
                        <Input
                            id="name"
                            value={data.name}
                            onChange={handleNameChange}
                            required
                        />
                        {errors.name && (
                            <p className="text-sm text-red-500">{errors.name}</p>
                        )}
                        <span className="text-xs italic">
                            Only alphanumeric characters, dashes, and underscores are allowed
                        </span>
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="databases">Select Databases</Label>
                        <select
                            id="databases"
                            multiple={true}
                            value={data.databases}
                            onChange={handleSelectChange}
                            className="w-full h-[150px] rounded-md border border-input bg-background px-3 py-2"
                        >
                            {databases.map((db) => (
                                <option key={db.id} value={db.id.toString()}>
                                    {db.database_name}
                                </option>
                            ))}
                        </select>
                        {errors.databases && (
                            <p className="text-sm text-red-500">{errors.databases}</p>
                        )}
                        <p className="text-sm text-muted-foreground">
                            Hold Ctrl (or Cmd on Mac) to select multiple databases
                        </p>
                    </div>

                    <Button type="submit" disabled={processing} className="w-full">
                        {processing ? "Creating..." : "Create Group"}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    )
}
