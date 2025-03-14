import type React from "react"

import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { useCustomEvent } from "@/hooks/use-custom-event"
import { type LibSQLDatabases } from "@/types"
import { useState } from "react"

export type CreateDatabaseProps = {
    database: string
    childDatabase: string
    isSchema: boolean | string
    useExisting: boolean
}

interface ModalCreateDatabaseProps {
    children?: React.ReactNode
    existingDatabases: LibSQLDatabases[]
    useExistingDatabase?: boolean
    onSubmit: (data: CreateDatabaseProps) => Promise<void>
}

export function ModalCreateDatabaseV2({ children, existingDatabases = [], useExistingDatabase = false, onSubmit }: ModalCreateDatabaseProps) {
    const [isOpen, setOpen] = useState(false)
    const [formData, setFormData] = useState<CreateDatabaseProps>({
        database: "",
        childDatabase: "",
        isSchema: false,
        useExisting: useExistingDatabase,
    })
    const [processing, setProcessing] = useState(false)

    const sharedDatabases = existingDatabases.filter((db) => db.is_schema)

    useCustomEvent<{ isModalOpen: boolean, parentDatabase: string }>('open-modal-changed', async ({ isModalOpen, parentDatabase }) => {
        setOpen(isModalOpen)
        setFormData({
            ...formData,
            useExisting: true,
            database: parentDatabase
        })
    });

    const handleSubmit = async (e: React.FormEvent) => {
        e.preventDefault()
        setProcessing(true)

        try {
            await onSubmit(formData)
            setOpen(false)
            setFormData({
                database: "",
                childDatabase: "",
                isSchema: false,
                useExisting: false,
            })
        } catch (error) {
            console.error("Error submitting form:", error)
        } finally {
            setProcessing(false)
        }
    }

    const handleInputChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const value = e.target.value.replace(/[^a-zA-Z0-9-_]/g, "")
        setFormData({ ...formData, database: value })
    }

    const handleSelectChange = (value: string) => {
        setFormData({ ...formData, database: value })
    }

    const handleChildDatabaseChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const value = e.target.value.replace(/[^a-zA-Z0-9-_]/g, "")
        setFormData({ ...formData, childDatabase: value })
    }

    const toggleUseExisting = (value: string) => {
        setFormData({
            ...formData,
            useExisting: value === "existing",
            database: value === "existing" ? "" : formData.database,
        })
    }

    return (
        <Dialog open={isOpen} onOpenChange={setOpen} >
            <DialogTrigger className="cursor-pointer hover:bg-primary hover:text-primary-foreground rounded-md" asChild onClick={() => setOpen(true)}>
                {children}
            </DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Database Management</DialogTitle>
                    <DialogDescription>Create a new database or select an existing shared schema database.</DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit} autoComplete="off" className="flex flex-col gap-4">
                    <RadioGroup
                        defaultValue="new"
                        value={formData.useExisting ? "existing" : "new"}
                        onValueChange={toggleUseExisting}
                        className="mb-2"
                    >
                        <div className="flex items-center space-x-2">
                            <RadioGroupItem value="new" id="new" />
                            <Label htmlFor="new">Create new database</Label>
                        </div>
                        <div className="flex items-center space-x-2">
                            <RadioGroupItem value="existing" id="existing" />
                            <Label htmlFor="existing">Use existing shared schema database</Label>
                        </div>
                    </RadioGroup>

                    {!formData.useExisting ? (
                        <div className="space-y-4">
                            <div className="w-full items-center gap-1.5">
                                <Input
                                    type="text"
                                    id="database"
                                    name="database"
                                    value={formData.database}
                                    onChange={handleInputChange}
                                    placeholder="Database Name"
                                    className="w-full"
                                    autoFocus
                                    tabIndex={1}
                                    required
                                />
                                <span className="text-xs italic">
                                    Only alphanumeric characters, dashes, and underscores are allowed
                                </span>
                            </div>
                            <div className="flex items-center space-x-3">
                                <Checkbox
                                    id="isSchema"
                                    name="isSchema"
                                    checked={formData.isSchema !== false}
                                    onCheckedChange={(checked) => setFormData({ ...formData, isSchema: checked === true })}
                                    tabIndex={2}
                                />
                                <Label htmlFor="isSchema">Schema Database</Label>
                            </div>
                        </div>
                    ) : (
                        <div className="space-y-4">
                            <div className="w-full">
                                <Select value={formData.database} onValueChange={handleSelectChange} required>
                                    <SelectTrigger className="w-full">
                                        <SelectValue placeholder="Select a Shared Schema Database" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {sharedDatabases.length > 0 ? (
                                            sharedDatabases.map((db) => (
                                                <SelectItem key={db.database_name} value={db.database_name}>
                                                    {db.database_name}
                                                </SelectItem>
                                            ))
                                        ) : (
                                            <SelectItem value="none" disabled>
                                                No shared schema databases available
                                            </SelectItem>
                                        )}
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="w-full items-center gap-1.5">
                                <Input
                                    type="text"
                                    id="childDatabase"
                                    name="childDatabase"
                                    value={formData.childDatabase}
                                    onChange={handleChildDatabaseChange}
                                    placeholder="Child Database Name"
                                    className="w-full"
                                    autoFocus
                                    tabIndex={1}
                                    required
                                />
                                <span className="text-xs italic">
                                    Only alphanumeric characters, dashes, and underscores are allowed
                                </span>
                            </div>
                        </div>
                    )}

                    <Button variant="default" type="submit" disabled={processing || !formData.database}>
                        {processing ? "Processing..." : formData.useExisting ? "Select Database" : "Create Database"}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    )
}
