"use client"

import { Check, ChevronsUpDown, PlusCircle } from "lucide-react"
import * as React from "react"

import { Button } from "@/components/ui/button"
import {
    Command,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
    CommandSeparator,
} from "@/components/ui/command"
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Popover, PopoverContent, PopoverTrigger } from "@/components/ui/popover"
import { useInitials } from "@/hooks/use-initials"
import { useLocalStorage } from "@/hooks/use-localstorage"
import { createSignal, useSignal } from "@/hooks/use-signal"
import { apiFetch } from "@/lib/api"
import { cn } from "@/lib/utils"
import { SharedData, Team } from "@/types"
import { router, usePage } from "@inertiajs/react"

type PopoverTriggerProps = React.ComponentPropsWithoutRef<typeof PopoverTrigger>

interface TeamSwitcherProps extends PopoverTriggerProps { }

export const teamSignal = createSignal<Team | null>(null)

export function TeamSwitcher({ className }: TeamSwitcherProps) {
    const { auth } = usePage<SharedData>().props
    const [currentTeamId, setCurrentTeamId] = useLocalStorage<number | null>('currentTeamId', auth.user.teams[0].id || null)

    const getInitials = useInitials();
    const [open, setOpen] = React.useState(false)
    const [showNewTeamDialog, setShowNewTeamDialog] = React.useState(false)
    const [selectedTeam, setSelectedTeam] = useSignal<Team | null>(teamSignal)

    const handleTeamChanged = async (team: Team) => {
        setSelectedTeam(team)
        setCurrentTeamId(team.id)
        setOpen(false)
        const response = await apiFetch(route('api.teams.databases', team.id))
        if (response.ok) {
            router.visit(window.location.href, {
                preserveScroll: true,
            })
        }
    }

    React.useEffect(() => {
        const teams = auth.user.teams
        if (teams.length > 0) {
            const team = teams.find(team => team.id === Number(currentTeamId)) as Team
            (async () => await apiFetch(route('api.teams.databases', team.id)))()
            setSelectedTeam(team)
            setCurrentTeamId(team.id)
        }
    }, [auth.user.teams])

    if (!selectedTeam || auth.user.teams.length === 0) {
        return null
    }

    return (
        <Dialog open={showNewTeamDialog} onOpenChange={setShowNewTeamDialog}>
            <Popover open={open} onOpenChange={setOpen}>
                <PopoverTrigger asChild>
                    <Button
                        variant="ghost"
                        size="default"
                        role="combobox"
                        aria-expanded={open}
                        aria-label="Select a team"
                        className={cn("w-full justify-between", className)}
                    >
                        <div className="flex items-center gap-2">
                            <span className="h-8 w-8 rounded-full bg-muted flex items-center justify-center">
                                {getInitials(selectedTeam.name)}
                            </span>
                            <span className="truncate">{selectedTeam.name}</span>
                        </div>
                        <ChevronsUpDown className="ml-auto h-4 w-4 shrink-0 opacity-50" />
                    </Button>
                </PopoverTrigger>
                <PopoverContent className="w-[200px] p-0">
                    <Command>
                        <CommandList>
                            <CommandInput placeholder="Search team..." />
                            <CommandEmpty>No team found.</CommandEmpty>
                            <CommandGroup heading="Teams">
                                {auth.user.teams.map((team) => (
                                    <CommandItem
                                        key={team.id}
                                        onSelect={() => handleTeamChanged(team)}
                                        className="text-sm"
                                    >
                                        <div className="flex items-center gap-2">
                                            <span>{team.name}</span>
                                        </div>
                                        <Check
                                            className={cn("ml-auto h-4 w-4", selectedTeam.id === team.id ? "opacity-100" : "opacity-0")}
                                        />
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        </CommandList>
                        <CommandSeparator />
                        <CommandList>
                            <CommandGroup>
                                <CommandItem
                                    onSelect={() => {
                                        setOpen(false)
                                        setShowNewTeamDialog(true)
                                    }}
                                >
                                    <PlusCircle className="mr-2 h-4 w-4" />
                                    Create Team
                                </CommandItem>
                            </CommandGroup>
                        </CommandList>
                    </Command>
                </PopoverContent>
            </Popover>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create team</DialogTitle>
                    <DialogDescription>Add a new team to manage databases and users.</DialogDescription>
                </DialogHeader>
                <div>
                    <div className="space-y-4 py-2 pb-4">
                        <div className="space-y-2">
                            <Label htmlFor="name">Team name</Label>
                            <Input id="name" placeholder="Acme Inc." />
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="description">Team description</Label>
                            <Input id="description" placeholder="Your team description" />
                        </div>
                    </div>
                </div>
                <DialogFooter>
                    <Button variant="outline" onClick={() => setShowNewTeamDialog(false)}>
                        Cancel
                    </Button>
                    <Button type="submit">Continue</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    )
}

