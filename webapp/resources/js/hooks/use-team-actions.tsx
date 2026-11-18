import { CreateDatabaseProps } from "@/components/modals/modal-create-database"
import { apiFetch } from "@/lib/api"
import { getCurrentTeamId } from "@/lib/current-team"
import { router, usePage } from "@inertiajs/react"
import { useState } from "react"
import { toast } from "sonner"

import type { ComboboxOption } from "@/components/ui/combobox"
import { CreateGroupOnlyForm } from "@/components/modals/modal-create-group-only"
import type { MemberForm, SharedData, Team, TeamForm } from "@/types"

/**
 * Shared command handlers for the teams UI — every mutation the Teams page
 * and the team cards can trigger lives here exactly once, instead of being
 * copy-pasted between tables/teams.tsx and team/team-card.tsx.
 *
 * Notes on failure semantics: `apiFetch` throws on non-2xx with the server's
 * message (or the first validation message from `errors`), so "fire and
 * inspect response.ok" is dead code — this module always try/catches.
 */
export function useTeamActions({ team }: { team: Team }) {
    const { csrfToken, groups: databaseGroups } = usePage<SharedData>().props
    const groupedDatabases = (databaseGroups || [])
        .map?.(group => ({
            label: group.name,
            value: group.id.toString()
        }))
        ?.sort((a, b) => Number(b.value) - Number(a.value)) || []
    const [groups, setGroups] = useState<ComboboxOption[]>(groupedDatabases)

    const handleCreateGroup = async (name: string): Promise<string> => {
        const teamId = getCurrentTeamId()
        const response = await apiFetch(route('api.group.create-only'), {
            method: 'POST',
            body: JSON.stringify({ name, team_id: teamId }),
            headers: { 'X-CSRF-TOKEN': csrfToken }
        })

        // apiFetch throws on non-2xx, so reaching here means success.
        const data = (await response.json()) as { group: { id: string } }
        const newGroupId = data.group.id
        const newGroup: ComboboxOption = { value: String(newGroupId), label: name }
        setGroups((prev) => [...prev, newGroup].sort((a, b) => Number(b.value) - Number(a.value)))

        return newGroupId
    }

    const handleDatabaseSubmit = async (formData: CreateDatabaseProps) => {
        const teamId = formData.teamId || getCurrentTeamId()
        const submittedData = {
            database: formData.useExisting ? formData.childDatabase : formData.database,
            isSchema: formData.useExisting ? formData.database : formData.isSchema,
            groupId: Number(formData.groupId),
            teamId: Number(teamId),
        }

        await apiFetch(route('database.create'), {
            method: 'POST',
            body: JSON.stringify(submittedData),
            headers: { 'X-CSRF-TOKEN': csrfToken }
        })

        // Best-effort refresh — session staleness must not mask a created DB.
        await apiFetch(route('api.teams.databases', Number(teamId)), {
            method: 'GET',
            headers: { 'X-CSRF-TOKEN': csrfToken }
        }).catch(() => { /* non-fatal */ })

        router.visit(window.location.href, { preserveScroll: true })
    }

    const handleRestoreTeam = (teamId: number) => {
        router.put(route('team.restore', teamId), { preserveScroll: true }, {
            onSuccess: (page) => {
                const flash = (page.props as { flash?: { success?: string; error?: string } }).flash

                if (flash?.success) {
                    toast.success(flash.success, { duration: 2500, position: 'top-center' })
                } else if (flash?.error) {
                    toast.error(flash.error, { duration: 2500, position: 'top-center' })
                }

                router.visit(route('dashboard.teams'), { preserveScroll: true })
            },
            onError: () => toast.error('Failed to restore team'),
        })
    }

    const handleEditTeamOnSave = async (formData: TeamForm) => {
        await apiFetch(route('team.update', team.id), {
            method: 'PUT',
            body: JSON.stringify(formData),
            headers: { 'X-CSRF-TOKEN': csrfToken }
        })

        router.visit(window.location.href, { preserveScroll: true })
    }

    const handleAddMember = async (member: MemberForm) => {
        try {
            await apiFetch(route('teams.invitations.store', team.id), {
                method: 'POST',
                body: JSON.stringify(member),
                headers: { 'X-CSRF-TOKEN': csrfToken }
            })

            toast.success(`Invitation sent to ${member.email}`)
            router.visit(window.location.href, { preserveScroll: true })
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Failed to send invitation')
        }
    }

    const handleCreateGroupSubmit = async (groupOnlyForm: CreateGroupOnlyForm): Promise<boolean> => {
        try {
            await apiFetch(route('api.group.create-only'), {
                method: 'POST',
                body: JSON.stringify({ name: groupOnlyForm.groupName, team_id: Number(groupOnlyForm.teamId) }),
                headers: { 'X-CSRF-TOKEN': csrfToken }
            })

            await apiFetch(route('api.teams.databases', Number(groupOnlyForm.teamId)), {
                method: 'GET',
                headers: { 'X-CSRF-TOKEN': csrfToken }
            }).catch(() => { /* non-fatal */ })

            // New group is rendered after the reload below.
            router.visit(window.location.href, { preserveScroll: true })

            return true
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Failed to create group')

            return false
        }
    }

    const handleUpdateRole = async (userId: number, role: string) => {
        router.put(route('teams.members.update-role', {
            team: team.id,
            user: userId
        }), { role }, {
            onSuccess: () => {
                router.visit(window.location.href, { preserveScroll: true })
            },
            onError: () => toast.error('Failed to update role'),
        })
    }

    const handleRemoveMember = async (userId: number, teamId: number) => {
        try {
            await apiFetch(route('teams.members.delete', { team: teamId, user: userId }), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken }
            })

            toast.success('Member removed')
            router.visit(window.location.href, { preserveScroll: true })
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Failed to remove member')
        }
    }

    const handleResendInvitation = async (invitationId: number) => {
        try {
            await apiFetch(route('teams.invitations.resend', { invitation: invitationId }), {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken }
            })

            toast.success('Invitation re-sent — the previous link no longer works')
            router.visit(window.location.href, { preserveScroll: true })
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Failed to re-send invitation')
        }
    }

    const handleRevokeInvitation = async (invitationId: number) => {
        try {
            await apiFetch(route('teams.invitations.destroy', { invitation: invitationId }), {
                method: 'DELETE',
                headers: { 'X-CSRF-TOKEN': csrfToken }
            })

            toast.success('Invitation revoked')
            router.visit(window.location.href, { preserveScroll: true })
        } catch (error) {
            toast.error(error instanceof Error ? error.message : 'Failed to revoke invitation')
        }
    }

    /**
     * Archive with a real confirmation — the blast radius is a whole team
     * (groups, databases, shared credentials, pending invitations), not one row.
     */
    const handleDeleteTeam = (teamId: number) => {
        toast(`Archive “${team.name}”?`, {
            description: 'Members will lose access immediately. If this is your current team, you will be switched to another one.',
            duration: 7000,
            position: 'top-center',
            action: (
                <ArchiveTeamAction team={team} csrfToken={csrfToken} />
            )
        })
    }

    return {
        groups,
        handleCreateGroup,
        handleDatabaseSubmit,
        handleRestoreTeam,
        handleEditTeamOnSave,
        handleAddMember,
        handleCreateGroupSubmit,
        handleUpdateRole,
        handleRemoveMember,
        handleResendInvitation,
        handleRevokeInvitation,
        handleDeleteTeam,
    }
}

/**
 * The toast renders outside the Inertia component tree, so this must not
 * read Inertia context (usePage would throw). Everything it needs arrives
 * via props.
 */
function ArchiveTeamAction({ team, csrfToken }: { team: Team; csrfToken: string }) {
    return (
        <button
            type="button"
            className="inline-flex items-center justify-center rounded-md bg-destructive px-3 py-1.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-destructive/90"
            onClick={async () => {
                try {
                    const response = await apiFetch(route('team.delete', team.id), {
                        method: 'DELETE',
                        headers: { 'X-CSRF-TOKEN': csrfToken }
                    })
                    const data = (await response.json()) as { message?: string }

                    toast.success(data.message ?? 'Team archived', {
                        duration: 2500,
                        position: 'top-center',
                    })
                    toast.info('If this was your current team, it has been switched to another team automatically.', {
                        duration: 4500,
                        position: 'top-center',
                    })
                    router.visit(window.location.href, { preserveScroll: true })
                } catch {
                    toast.error('Failed to archive team')
                }
            }}
        >
            Archive
        </button>
    )
}
