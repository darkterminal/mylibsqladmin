"use client"
import {
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from "@/components/ui/command"
import { databaseType } from "@/lib/utils"
import { LibSQLDatabases } from "@/types"
import { Cylinder, File, GitBranch, Search } from "lucide-react"
import { useCallback, useEffect, useState } from "react"

interface DatabaseCommandProps {
    databases: LibSQLDatabases[]
    onSelect: (database: LibSQLDatabases) => void
    triggerShortcut?: string
}

export function DatabaseCommand({ databases, onSelect, triggerShortcut = "ctrl+k" }: DatabaseCommandProps) {
    const [open, setOpen] = useState(false)
    const [search, setSearch] = useState("")

    useEffect(() => {
        const down = (e: KeyboardEvent) => {
            const isCtrlK = triggerShortcut === "ctrl+k" && e.ctrlKey && e.key === "k"
            const isMetaK = triggerShortcut === "meta+k" && e.metaKey && e.key === "k"

            if (isCtrlK || isMetaK) {
                e.preventDefault()
                setOpen((open) => !open)
            }
        }

        document.addEventListener("keydown", down)
        return () => document.removeEventListener("keydown", down)
    }, [triggerShortcut])

    const handleSelect = useCallback(
        (database: LibSQLDatabases) => {
            onSelect(database)
            setOpen(false)
        },
        [onSelect],
    )

    return (
        <>
            <button
                onClick={() => setOpen(true)}
                className="inline-flex items-center justify-center rounded-md text-sm font-medium ring-offset-background transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:pointer-events-none disabled:opacity-50 border border-input bg-background hover:bg-accent hover:text-accent-foreground px-4 py-2 gap-2"
            >
                <Search className="mr-2 h-4 w-4" />
                <span>Search databases...</span>
                <kbd className="pointer-events-none inline-flex h-5 select-none items-center gap-1 rounded border bg-muted px-1.5 font-mono text-[10px] font-medium text-muted-foreground ml-auto">
                    {triggerShortcut.replace("ctrl", "⌃").replace("meta", "⌘").replace("+", " ")}
                </kbd>
            </button>

            <CommandDialog open={open} onOpenChange={setOpen}>
                <CommandInput placeholder="Search databases..." value={search} onValueChange={setSearch} />
                <CommandList>
                    <CommandEmpty>No databases found.</CommandEmpty>
                    <CommandGroup heading="Databases">
                        {databases
                            .filter((db) => db.database_name.toLowerCase().includes(search.toLowerCase()))
                            .map((database) => {
                                const dbType = databaseType(String(database.is_schema))
                                return (
                                    <CommandItem
                                        key={`${database.user_id}-${database.database_name}`}
                                        onSelect={() => handleSelect(database)}
                                        className="flex items-center cursor-pointer"
                                    >
                                        {dbType === "schema" && (
                                            <File className="mr-2 h-4 w-4 text-primary" />
                                        )}
                                        {dbType === "standalone" && (
                                            <Cylinder className="mr-2 h-4 w-4 text-primary" />
                                        )}
                                        {dbType === database.is_schema && (
                                            <GitBranch className="mr-2 h-4 w-4 text-primary" />
                                        )}
                                        <span>{database.database_name}</span>
                                        {dbType === "schema" && (
                                            <span className="ml-auto text-xs text-muted-foreground">schema</span>
                                        )}
                                        {dbType === "0" && (
                                            <span className="ml-auto text-xs text-muted-foreground">standalone</span>
                                        )}
                                        {dbType === database.is_schema && (
                                            <span className="ml-auto text-xs text-muted-foreground">child of {database.is_schema}</span>
                                        )}
                                    </CommandItem>
                                )
                            })}
                    </CommandGroup>
                </CommandList>
            </CommandDialog>
        </>
    )
}
