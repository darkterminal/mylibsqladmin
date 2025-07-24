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
import { usePermission } from "@/lib/auth"
import { cn } from "@/lib/utils"
import { SharedData, Team } from "@/types"
import { router, useForm, usePage } from "@inertiajs/react"
import { toast } from "sonner"

type PopoverTriggerProps = React.ComponentPropsWithoutRef<typeof PopoverTrigger>

interface TeamSwitcherProps extends PopoverTriggerProps { }

export const teamSignal = createSignal<Team | null>(null)

export function TeamSwitcher({ className }: TeamSwitcherProps) {
    const { auth, csrfToken } = usePage<SharedData>().props
    const [currentTeamId, setCurrentTeamId] = useLocalStorage<number | null | undefined>('currentTeamId', auth.user.teams[0]?.id || null)

    const getInitials = useInitials();
    const { can } = usePermission();
    const [open, setOpen] = React.useState(false)
    const [showNewTeamDialog, setShowNewTeamDialog] = React.useState(false)
    const [selectedTeam, setSelectedTeam] = useSignal<Team | null>(teamSignal)

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        description: ''
    })

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault()
        post(route('team.create'), {
            preserveScroll: true,
            onSuccess: (page) => {
                setSelectedTeam(null)
                setCurrentTeamId(page.props.flash.newTeam)
                setShowNewTeamDialog(false)
                setData({ name: '', description: '' })
                if (page.props.flash.success) {
                    toast.success(page.props.flash.success, {
                        position: 'bottom-center',
                        duration: 2500,
                        onAutoClose: () => {
                            router.visit(window.location.href, {
                                preserveScroll: true
                            })
                        },
                        onDismiss: () => {
                            router.visit(window.location.href, {
                                preserveScroll: true
                            })
                        }
                    })
                } else {
                    toast.error('Team failed to be created', {
                        position: 'bottom-center',
                        duration: 2500,
                        onAutoClose: () => {
                            router.visit(window.location.href, {
                                preserveScroll: true
                            })
                        },
                        onDismiss: () => {
                            router.visit(window.location.href, {
                                preserveScroll: true
                            })
                        }
                    })
                }
            }
        })
    }

    const handleTeamChanged = async (team: Team) => {
        setSelectedTeam(team)
        setCurrentTeamId(team.id)
        setOpen(false)
        const response = await apiFetch(route('api.teams.databases', team.id), {
            method: 'GET',
            headers: {
                'X-CSRF-TOKEN': csrfToken
            }
        })
        if (response.ok) {
            router.visit(window.location.href, {
                preserveScroll: true,
            })
        }
    }

    React.useEffect(() => {
        if (auth.user.teams.length > 0) {
            const team = auth.user.teams.find(team => team.id === Number(currentTeamId)) || auth.user.teams[0]
            if (team) {
                (async () => await apiFetch(route('api.teams.databases', team.id), {
                    method: 'GET',
                    headers: {
                        'X-CSRF-TOKEN': csrfToken
                    }
                }))()
                setSelectedTeam(team)
                setCurrentTeamId(team.id)
            }
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
                                {getInitials(selectedTeam?.name || "")}
                            </span>
                            <span className="truncate">{selectedTeam?.name || ""}</span>
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
                                            className={cn("ml-auto h-4 w-4", selectedTeam?.id === team.id ? "opacity-100" : "opacity-0")}
                                        />
                                    </CommandItem>
                                ))}
                            </CommandGroup>
                        </CommandList>
                        {can('manage-teams') && (
                            <>
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
                            </>
                        )}
                    </Command>
                </PopoverContent>
            </Popover>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create team</DialogTitle>
                    <DialogDescription>Add a new team to manage databases and users.</DialogDescription>
                </DialogHeader>
                <form onSubmit={handleSubmit}>
                    <div className="space-y-4 py-2 pb-4">
                        <div className="space-y-2">
                            <Label htmlFor="name">Team name</Label>
                            <Input
                                id="name"
                                value={data.name}
                                onChange={e => setData('name', e.target.value)}
                                placeholder="Acme Inc."
                            />
                            {errors.name && (
                                <p className="text-sm text-red-500">{errors.name}</p>
                            )}
                        </div>
                        <div className="space-y-2">
                            <Label htmlFor="description">Team description</Label>
                            <Input
                                id="description"
                                value={data.description}
                                onChange={e => setData('description', e.target.value)}
                                placeholder="Your team description"
                            />
                            {errors.description && (
                                <p className="text-sm text-red-500">{errors.description}</p>
                            )}
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setShowNewTeamDialog(false)}
                            type="button"
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={processing}>
                            {processing ? 'Creating...' : 'Create'}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    )
}

