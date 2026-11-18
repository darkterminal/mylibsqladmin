import TokenCopyRow from "@/components/token-copy-row"
import { Badge } from "@/components/ui/badge"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Input } from "@/components/ui/input"
import { triggerEvent } from "@/hooks/use-custom-event"
import { apiFetch } from "@/lib/api"
import { usePermission } from "@/lib/auth"
import { DatabaseInGroupProps, GroupDatabaseProps, GroupMemberTokenProps, SharedData } from "@/types"
import { router, usePage } from "@inertiajs/react"
import { CirclePlusIcon, DatabaseIcon, KeyIcon, Pencil, PlusCircleIcon, Server, ShieldOff } from "lucide-react"
import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"
import { AppTooltip } from "./app-tooltip"
import ButtonDelete from "./button-actions/action-delete"
import ButtonOpenDatabaseStudio from "./button-actions/action-open-database-studio"
import { CreateDatabaseProps, ModalCreateDatabase } from "./modals/modal-create-database"
import ModalAddDatabaseToGroup from "./modals/modal-add-database-to-group"
import { ModalCreateGroupToken } from "./modals/modal-create-group-token"
import { Button } from "./ui/button"
import { ComboboxOption } from "./ui/combobox"
import { ScrollArea } from "./ui/scroll-area"
import { getCurrentTeamId } from "@/lib/current-team"

function tokenState(token: GroupMemberTokenProps): { label: string; title?: string } {
    if (token.expiration_state === 'expired') {
        return { label: `expired ${token.expires_at ?? ''}` }
    }
    if (token.expiration_state === 'never') {
        return { label: 'never expires' }
    }

    return { label: `expires ${token.expires_at}` }
}

export default function GroupDetail({
    group: initialGroup,
    availableDatabases
}: {
    group: GroupDatabaseProps | null
    availableDatabases: DatabaseInGroupProps[]
}) {
    const { can } = usePermission();
    const { csrfToken, databases, groups: databaseGroups, flash } = usePage<SharedData>().props;
    const newGroupToken = (flash as { newGroupToken?: import('@/types').GroupDatabaseTokenProps }).newGroupToken;
    const groupedDatabases = (databaseGroups || [])
        .map?.(group => ({
            label: group.name,
            value: group.id.toString()
        }))
        ?.sort((a, b) => Number(b.value) - Number(a.value)) || [];
    const [groups, setGroups] = useState<ComboboxOption[]>(groupedDatabases);
    const [isLoading, setIsLoading] = useState(true);
    const [group, setGroup] = useState<GroupDatabaseProps | null>(null);
    const [renaming, setRenaming] = useState(false);
    const [draftName, setDraftName] = useState("");

    useEffect(() => {
        if (initialGroup) {
            setGroup(initialGroup);
            setDraftName(initialGroup.name);
            setIsLoading(false);
        }
    }, [initialGroup]);

    const deleteGroup = useCallback(() => {
        if (!group) return;

        toast(`Permanently delete “${group.name}”?`, {
            description: "The group and its shared group token will be permanently deleted. The databases themselves are NOT deleted — they just lose the group.",
            action: (
                <Button
                    variant="destructive"
                    size="sm"
                    onClick={() => {
                        router.delete(route('group.delete', { group: group.id }), {
                            preserveScroll: true,
                            onSuccess: () => {
                                triggerEvent('database-group-is-deleted', { id: group.id });
                                toast.dismiss();
                            }
                        });
                    }}
                >
                    Delete
                </Button>
            )
        });
    }, [group?.id, group?.name]);

    const deleteGroupToken = useCallback(() => {
        if (!group?.group_token) return;

        toast("Permanently delete the group token?", {
            description: "Applications using this shared credential will lose access immediately. This cannot be undone.",
            duration: 7000,
            position: "top-center",
            action: (
                <Button
                    variant="destructive"
                    size="sm"
                    onClick={() => {
                        router.delete(route('group.token.delete', { tokenId: group.group_token!.id }), {
                            onSuccess: () => {
                                toast.dismiss();
                            }
                        });
                    }}
                >
                    Delete
                </Button>
            ),
        });
    }, [group?.group_token]);

    const handleOnSuccess = useCallback(() => {
        // The reveal panel renders from flash.newGroupToken in the detail
        // card — just acknowledge it. The groups payload already refreshed
        // via the POST redirect response.
        toast.success('Group token created');
    }, []);

    const getDatabaseToken = useCallback((databaseId: number) => {
        return group?.database_tokens?.find(t => t.database_id === databaseId) ?? null;
    }, [group?.database_tokens]);

    const handleDatabaseSubmit = async (formData: CreateDatabaseProps) => {
        const teamId = getCurrentTeamId();

        const submittedData = {
            database: formData.useExisting ? formData.childDatabase : formData.database,
            isSchema: formData.useExisting ? formData.database : formData.isSchema,
            groupId: Number(formData.groupId),
            teamId: Number(teamId),
        };

        const response = await apiFetch(route('database.create'), {
            method: 'POST',
            body: JSON.stringify(submittedData),
            headers: {
                'X-CSRF-TOKEN': csrfToken
            }
        });

        if (response.ok) {
            router.visit(window.location.href, {
                preserveScroll: true,
            });
        }
    }

    const submitRename = useCallback(() => {
        setRenaming(false);

        if (!group) return;

        const name = draftName.trim();

        if (!name || name === group.name) {
            setDraftName(group.name);
            return;
        }

        router.patch(route('group.rename', { group: group.id }), { name }, {
            preserveScroll: true,
            onSuccess: () => toast.success('Group renamed'),
            onError: () => {
                setDraftName(group.name);
                toast.error('Failed to rename group');
            },
        });
    }, [draftName, group?.id, group?.name]);

    const handleDeleteDatabase = useCallback((database: { id?: number; database_name?: string }) => {
        if (!database?.id) return;

        toast(`Remove “${database.database_name}” from this group?`, {
            description: 'The database itself is NOT deleted — it just leaves this group.',
            position: 'top-center',
            action: (
                <Button
                    variant="destructive"
                    size="sm"
                    onClick={() => {
                        router.delete(route('group.detach-database', { database: database.id }), {
                            preserveScroll: true,
                            onSuccess: () => {
                                toast.success('Removed from group');
                            },
                            onFinish: () => {
                                router.visit(route('dashboard.groups'));
                            }
                        });
                    }}
                >
                    Remove
                </Button>
            )
        })
    }, []);

    if (isLoading || !group) {
        return <div className="p-4 text-center text-muted-foreground">Loading group details...</div>;
    }

    if (!group.members?.length) {
        return (
            <div className="flex h-full min-h-64 flex-col items-center justify-center gap-4 rounded-md border p-8 text-center">
                <div>
                    <h3 className="font-medium">No databases in “{group.name}” yet</h3>
                    <p className="mt-1 text-sm text-muted-foreground">Add one to start querying it as part of this group.</p>
                </div>
                <div className="flex flex-wrap items-center justify-center gap-3">
                    <ModalCreateDatabase
                        existingDatabases={databases}
                        onSubmit={handleDatabaseSubmit}
                        groups={groups}
                        currentGroup={group}
                    >
                        <AppTooltip text='Create new database'>
                            <Button variant={'default'} size={'default'}>
                                <CirclePlusIcon className='h-4 w-4' aria-hidden="true" /> Add New Database
                            </Button>
                        </AppTooltip>
                    </ModalCreateDatabase>
                    {can('manage-groups') && (
                        <ModalAddDatabaseToGroup groupId={group.id} databases={availableDatabases}>
                            <Button variant="outline">
                                <PlusCircleIcon className="h-4 w-4" aria-hidden="true" /> Add Existing
                            </Button>
                        </ModalAddDatabaseToGroup>
                    )}
                    {can('delete-groups') && <ButtonDelete text="Delete Group" handleDelete={deleteGroup} />}
                </div>
            </div>
        );
    }

    return (
        <Card>
            <CardHeader className="pb-3">
                <div className="flex items-start justify-between gap-3">
                    <div className="flex min-w-0 flex-col gap-1">
                        <div className="flex min-w-0 items-center gap-2">
                            <Server className="h-5 w-5 shrink-0 text-muted-foreground" aria-hidden="true" />
                            {renaming ? (
                                <Input
                                    value={draftName}
                                    autoFocus
                                    aria-label="Group name"
                                    className="h-8 text-base font-semibold"
                                    onChange={(e) => setDraftName(e.target.value)}
                                    onBlur={submitRename}
                                    onKeyDown={(e) => {
                                        if (e.key === 'Enter') submitRename();
                                        if (e.key === 'Escape') { setDraftName(group.name); setRenaming(false); }
                                    }}
                                />
                            ) : (
                                <>
                                    <CardTitle className="truncate text-xl" title={group.name}>{group.name}</CardTitle>
                                    {can('update-groups') && (
                                        <button
                                            type="button"
                                            className="shrink-0 rounded-md p-1 text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                                            aria-label="Rename group"
                                            onClick={() => setRenaming(true)}
                                        >
                                            <Pencil className="h-4 w-4" aria-hidden="true" />
                                        </button>
                                    )}
                                </>
                            )}
                        </div>
                        <CardDescription className="pl-7">
                            {group.members_count} {group.members_count === 1 ? "database" : "databases"} in this group
                            {group.has_token && group.group_token && (
                                <span className="ml-2 inline-flex align-middle">
                                    {group.group_token.expiration_state === 'expired'
                                        ? <Badge variant="destructive">Token expired</Badge>
                                        : <Badge variant="outline">Token active until {group.group_token.expires_at ?? '—'}</Badge>}
                                </span>
                            )}
                        </CardDescription>
                    </div>
                    <div className="flex shrink-0 flex-wrap items-center justify-end gap-2">
                        {can('manage-group-tokens') && (
                            <ModalCreateGroupToken groupId={group.id} onSuccess={handleOnSuccess}>
                                <AppTooltip text={group.has_token ? "Regenerate group token (replaces the existing one)" : "Create group token"}>
                                    <Button variant="default" size="icon" aria-label={group.has_token ? "Regenerate group token" : "Create group token"}>
                                        <KeyIcon className="h-4 w-4" aria-hidden="true" />
                                    </Button>
                                </AppTooltip>
                            </ModalCreateGroupToken>
                        )}
                        {group.has_token && group.group_token && can('manage-group-tokens') && (
                            <AppTooltip text="Delete group token">
                                <Button
                                    variant="destructive"
                                    size="icon"
                                    aria-label="Delete group token"
                                    onClick={deleteGroupToken}
                                >
                                    <ShieldOff className="h-4 w-4" aria-hidden="true" />
                                </Button>
                            </AppTooltip>
                        )}
                        {can('manage-groups') && (
                            <ModalAddDatabaseToGroup groupId={group.id} databases={availableDatabases}>
                                <AppTooltip text="Add Database to Group">
                                    <Button variant="outline" size="icon" aria-label="Add database to group">
                                        <PlusCircleIcon className="h-4 w-4" aria-hidden="true" />
                                    </Button>
                                </AppTooltip>
                            </ModalAddDatabaseToGroup>
                        )}
                        {can('delete-groups') && <ButtonDelete text="Delete Group" handleDelete={deleteGroup} />}
                    </div>
                </div>
            </CardHeader>
            {newGroupToken && (
                <CardContent>
                    <div className="flex flex-col gap-3 rounded-md border border-amber-500/60 bg-amber-50/60 p-3 dark:border-amber-500/40 dark:bg-amber-950/40">
                        <div>
                            <p className="text-xs font-semibold text-amber-900 dark:text-amber-200">
                                Group token created — copy it now. It will not be shown again after you leave this page.
                            </p>
                            <p className="mt-1 text-[11px] text-amber-800/80 dark:text-amber-300/80">
                                Both tokens are shown once, then only SHA-256 hashes are stored.
                            </p>
                        </div>
                        <TokenCopyRow label="Full Access Token" value={newGroupToken.full_access_token} />
                        <TokenCopyRow label="Read-only Token" value={newGroupToken.read_only_token} />
                        <Button
                            type="button"
                            className="w-full"
                            onClick={() => router.reload()}
                        >
                            I&apos;ve saved these tokens
                        </Button>
                    </div>
                </CardContent>
            )}
            <CardContent>
                <h3 className="mb-2 text-sm font-medium text-muted-foreground">Databases in this group</h3>
                <ScrollArea className="max-h-[60vh]">
                    <div className="grid gap-2">
                        {group.members.map((database) => {
                            if (!database.id || !database.database_name) return null;

                            const token = getDatabaseToken(database.id);

                            return (
                                <div key={database.id} className="flex items-center gap-3 rounded-md border p-3">
                                    <DatabaseIcon className="h-4 w-4 shrink-0 text-primary" aria-hidden="true" />
                                    <div className="min-w-0">
                                        <div className="truncate text-sm font-medium" title={database.database_name}>
                                            {database.database_name}
                                        </div>
                                        {token && (
                                            <div className="mt-0.5 flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                                                <span className="font-mono" title="Fingerprint (last 8 of the stored hash)">fp {token.fingerprint}</span>
                                                <span aria-hidden="true">·</span>
                                                <span>{token.name}</span>
                                                <span aria-hidden="true">·</span>
                                                {token.expiration_state === 'expired'
                                                    ? <Badge variant="destructive" className="text-[10px]">Expired {token.expires_at}</Badge>
                                                    : <span>{tokenState(token).label}</span>}
                                            </div>
                                        )}
                                    </div>
                                    <div className="ml-auto flex shrink-0 items-center gap-2">
                                        {!token && <span className="hidden text-xs text-muted-foreground sm:inline">No token</span>}
                                        <ButtonOpenDatabaseStudio databaseName={database.database_name} />
                                        {can('delete-databases') && (
                                            <ButtonDelete
                                                handleDelete={() => handleDeleteDatabase(database)}
                                                text="Remove from group"
                                            />
                                        )}
                                    </div>
                                </div>
                            )
                        })}
                    </div>
                </ScrollArea>
            </CardContent>
        </Card>
    )
}
