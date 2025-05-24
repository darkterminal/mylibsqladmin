import { Button } from "@/components/ui/button"
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogTrigger } from "@/components/ui/dialog"
import { Label } from "@/components/ui/label"
import { DatabaseInGroupProps } from "@/types"
import { router, useForm } from "@inertiajs/react"
import { DialogDescription } from "@radix-ui/react-dialog"
import type React from "react"
import { useState } from "react"

export default function ModalAddDatabaseToGroup({
    groupId,
    databases,
    children,
}: {
    groupId: number
    databases: DatabaseInGroupProps[]
    children: React.ReactNode
}) {
    const [open, setOpen] = useState(false)
    const { data, setData, post, processing, errors, reset } = useForm({
        databases: [] as string[],
    })

    const handleSelectChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const selectedOptions = Array.from(e.target.selectedOptions).map((option) => option.value)
        setData('databases', selectedOptions)
    }

    const submit = (e: React.FormEvent) => {
        e.preventDefault()
        post(route('group.add-databases', { group: groupId }), {
            preserveScroll: true,
            onSuccess: () => {
                reset()
                setOpen(false)
                router.visit(route('dashboard.groups'));
            },
        })
    }

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild disabled={databases.length === 0}>{children}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Add Databases to Group</DialogTitle>
                    <DialogDescription className="text-sm">
                        Select databases to add to this group
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4">
                    <div className="space-y-2">
                        <Label htmlFor="databases">Select Databases</Label>
                        <select
                            id="databases"
                            multiple
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
                    </div>

                    <Button type="submit" disabled={processing}>
                        {processing ? "Adding..." : "Add Databases"}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    )
}
