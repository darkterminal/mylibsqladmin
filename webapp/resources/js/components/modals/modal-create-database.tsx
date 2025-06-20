"use client"

import type React from "react"

import { Checkbox } from "@/components/ui/checkbox"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { ModalForm } from "@/components/ui/modal-form"
import { RadioGroup, RadioGroupItem } from "@/components/ui/radio-group"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { useCustomEvent } from "@/hooks/use-custom-event"
import { databaseType } from "@/lib/utils"
import { type LibSQLDatabases, type OpenModalStateChangeProps, type Team } from "@/types"
import { useEffect, useState } from "react"
import { Combobox, ComboboxOption } from "../ui/combobox"

export type CreateDatabaseProps = {
    database: string
    childDatabase: string
    isSchema: boolean | string
    useExisting: boolean
    groupId: string, // Changed from groupName to groupId
    teamId?: number | null
}

interface ModalCreateDatabaseProps {
    children?: React.ReactNode
    existingDatabases: LibSQLDatabases[]
    useExistingDatabase?: boolean
    onSubmit: (data: CreateDatabaseProps) => Promise<void>
    groups?: ComboboxOption[]
    onCreateGroup?: (name: string) => Promise<string>
    currentTeam?: Team
    currentGroup?: {
        id: number,
        name: string
    }
}

export function ModalCreateDatabase({
    children,
    existingDatabases = [],
    useExistingDatabase = false,
    onSubmit,
    groups = [],
    onCreateGroup,
    currentTeam,
    currentGroup
}: ModalCreateDatabaseProps) {
    const [isOpen, setOpen] = useState(false)
    const [formData, setFormData] = useState<CreateDatabaseProps>({
        database: "",
        childDatabase: "",
        isSchema: false,
        useExisting: useExistingDatabase,
        groupId: currentGroup?.id.toString() || "",
        teamId: currentTeam?.id
    });
    const [activeTeamId, setActiveTeamId] = useState<string | null>(null)
    const [processing, setProcessing] = useState(false)

    const getGroupOptions = (): ComboboxOption[] => {
        let options: ComboboxOption[] = currentTeam?.groups
            ? currentTeam.groups.map(g => ({
                label: g.name,
                value: g.id.toString()
            }))
            : [...groups];

        if (currentGroup && !options.some(o => o.value === currentGroup.id.toString())) {
            options.push({
                label: currentGroup.name,
                value: currentGroup.id.toString()
            });
        }

        return options.sort((a, b) => Number(b.value) - Number(a.value));
    };

    const [availableGroups, setAvailableGroups] = useState<ComboboxOption[]>(getGroupOptions())

    // Update availableGroups when teams/groups change
    useEffect(() => {
        setAvailableGroups(getGroupOptions());
    }, []);

    // Sync team-related data
    useEffect(() => {
        if (currentTeam) {
            setActiveTeamId(currentTeam.id.toString());
            setFormData(prev => ({
                ...prev,
                teamId: currentTeam.id,
            }));
        } else {
            setActiveTeamId(null);
        }
    }, [currentTeam]);

    // Sync group ID when currentGroup changes
    useEffect(() => {
        if (currentGroup?.id) {
            setFormData(prev => ({
                ...prev,
                groupId: currentGroup.id.toString(),
            }));
        }
    }, [currentGroup?.id]);

    const sharedDatabases = existingDatabases.filter((db) => databaseType(db.is_schema.toString()) === "schema");

    useCustomEvent<OpenModalStateChangeProps>("open-modal-changed", async ({ isModalOpen, parentDatabase }) => {
        setOpen(isModalOpen)
        setFormData({
            ...formData,
            useExisting: true,
            database: parentDatabase,
        })
    })

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
                groupId: currentGroup?.id?.toString() || "",
                teamId: currentTeam?.id
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

    const handleCreateGroup = async (name: string) => {
        if (onCreateGroup) {
            try {
                const newGroupId = await onCreateGroup(name)
                const newGroup: ComboboxOption = { value: newGroupId, label: name }
                setAvailableGroups((prev) => [...prev, newGroup])
                setFormData(prev => ({ ...prev, groupId: newGroupId }))
            } catch (error) {
                console.error("Error creating group:", error)
            }
        }
    }

    return (
        <ModalForm
            title="Database Management"
            description="Create a new database or select an existing shared schema database."
            trigger={children}
            isOpen={isOpen}
            onOpenChange={setOpen}
            onSubmit={handleSubmit}
            isSubmitting={processing}
            isSubmitDisabled={!formData.database || (formData.useExisting && !formData.childDatabase)}
            submitLabel={processing ? "Processing..." : formData.useExisting ? "Select Database" : "Create Database"}
        >
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

            <div className="space-y-4">
                <div className="space-y-2">
                    <Label htmlFor="groupId">Group</Label>
                    <Combobox
                        options={availableGroups}
                        value={formData.groupId}
                        onValueChange={(value) => {
                            setFormData(prev => ({
                                ...prev,
                                groupId: value
                            }))
                        }}
                        placeholder="Select a group"
                        emptyMessage="No groups found"
                        createNewOptionLabel="Create new group"
                        onCreateOption={handleCreateGroup}
                    />
                </div>

                {!formData.useExisting ? (
                    <div className="space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="database">Database Name</Label>
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
                            <span className="text-xs italic">Only alphanumeric characters, dashes, and underscores are allowed</span>
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
                        <div className="space-y-2">
                            <Label htmlFor="parentDatabase">Parent Database</Label>
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
                        <div className="space-y-2">
                            <Label htmlFor="childDatabase">Child Database Name</Label>
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
                            <span className="text-xs italic">Only alphanumeric characters, dashes, and underscores are allowed</span>
                        </div>
                    </div>
                )}
                <input type="hidden" name="teamId" value={activeTeamId?.toString()} />
            </div>
        </ModalForm>
    )
}
