import { Badge } from "@/components/ui/badge"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { triggerEvent } from "@/hooks/use-custom-event"
import { apiFetch } from "@/lib/api"
import { usePermission } from "@/lib/auth"
import { calculateExpirationDate, isTokenHash } from "@/lib/utils"
import { DatabaseInGroupProps, GroupDatabaseProps, SharedData } from "@/types"
import { router, usePage } from "@inertiajs/react"
import { CirclePlusIcon, DatabaseIcon, KeyIcon, PlusCircleIcon, Server } from "lucide-react"
import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"
import { AppTooltip } from "./app-tooltip"
import ButtonCopyFullAccessToken from "./button-actions/action-copy-full-access-token"
import ButtonCopyReadOnlyToken from "./button-actions/action-copy-read-only-token"
import ButtonDelete from "./button-actions/action-delete"
import ButtonActionGroupToken from "./button-actions/action-group-token"
import ButtonOpenDatabaseStudio from "./button-actions/action-open-database-studio"
import ModalAddDatabaseToGroup from "./modals/modal-add-database-to-group"
import { CreateDatabaseProps, ModalCreateDatabase } from "./modals/modal-create-database"
import { ModalCreateGroupToken } from "./modals/modal-create-group-token"
import { Button } from "./ui/button"
import { ComboboxOption } from "./ui/combobox"
import { ScrollArea } from "./ui/scroll-area"
import { getCurrentTeamId } from "@/lib/current-team"

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

    useEffect(() => {
        if (initialGroup) {
            setGroup(initialGroup);
            setIsLoading(false);
        }
    }, [initialGroup]);

    const deleteGroup = useCallback(() => {
        if (!group) return;

        toast('Are you sure you want to delete this group?', {
            description: "This action cannot be undone.",
            action: (
                <Button
                    variant="destructive"
                    size="sm"
                    onClick={() => {
                        router.delete(route('group.delete', { groupId: group.id }), {
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
    }, [group?.id]);

    const handleOnSuccess = useCallback(() => {
        if (!group) return;

        toast.success('Group token created successfully');

        router.reload({ only: ['databaseGroups'] });
        triggerEvent('group-token-is-created', { id: group.id });
    }, [group?.id]);

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

    const handleDeleteDatabase = useCallback((databaseId: number) => {
        toast('Are you sure you want to delete this database from this group?', {
            description: "This action cannot be undone.",
            position: 'top-center',
            action: (
                <Button
                    variant="destructive"
                    size="sm"
                    onClick={() => {
                        router.delete(route('group.delete-databases', { database: databaseId }), {
                            preserveScroll: true,
                            onSuccess: () => {
                                toast.success('Database deleted successfully');
                            },
                            onFinish: () => {
                                router.visit(route('dashboard.groups'));
                            }
                        });
                    }}
                >
                    Delete
                </Button>
            )
        })
    }, []);

    if (isLoading || !group) {
        return <div className="p-4 text-center text-muted-foreground">Loading group details...</div>;
    }

    if (!group.members?.length) {
        return (
            <div className="h-full flex items-center justify-center border rounded-md p-8 text-center">
                <div>
                    <h3 className="font-medium mb-2">There is no database in this group</h3>
                    <div className="flex gap-3">
                        <ModalCreateDatabase
                            existingDatabases={databases}
                            onSubmit={handleDatabaseSubmit}
                            groups={groups}
                            currentGroup={group}
                        >
                            <AppTooltip text='Create new database'>
                                <Button variant={'outline'} size={'default'}>
                                    <CirclePlusIcon className='h-4 w-4' /> Add New Database
                                </Button>
                            </AppTooltip>
                        </ModalCreateDatabase>
                        {can('delete-groups') && <ButtonDelete text="Delete Group" handleDelete={deleteGroup} />}
                    </div>
                </div>
            </div>
        );
    }

    const tokenExpirationDate = group.has_token ? calculateExpirationDate(group.group_token.created_at, group.group_token.expiration_day) : false;

    return (
        <Card>
            <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        {can('delete-groups') && <ButtonDelete text="Delete Group" handleDelete={deleteGroup} />}
                        <CardTitle className="text-xl flex items-center">
                            <Server className="h-5 w-5 text-muted-foreground mr-2" />
                            <span>{group.name}</span>
                        </CardTitle>
                        <CardDescription>
                            {group.members_count} {group.members_count === 1 ? "database" : "databases"} in this group
                            {group.has_token && (
                                <span className="ml-2">
                                    {tokenExpirationDate === 'expired' ? <Badge variant="destructive">Expired</Badge> : <Badge variant={'outline'}>Token Active until {tokenExpirationDate}</Badge>}
                                </span>
                            )}
                        </CardDescription>
                    </div>
                    <div className="flex gap-2">
                        {(group.has_token && tokenExpirationDate !== 'expired') && (
                            <ButtonActionGroupToken group_token={group.group_token} />
                        )}
                        {can('manage-group-tokens') && (
                            <ModalCreateGroupToken groupId={group.id} onSuccess={handleOnSuccess}>
                                <AppTooltip text={group.has_token ? "Revoke Group Token" : "Create Group Token"}>
                                    <Button variant="default">
                                        <KeyIcon className="h-4 w-4" />
                                    </Button>
                                </AppTooltip>
                            </ModalCreateGroupToken>
                        )}
                        {can('manage-groups') && (
                            <ModalAddDatabaseToGroup groupId={group.id} databases={availableDatabases}>
                                <AppTooltip text="Add Database to Group">
                                    <Button variant="default">
                                        <PlusCircleIcon className="h-4 w-4" />
                                    </Button>
                                </AppTooltip>
                            </ModalAddDatabaseToGroup>
                        )}
                    </div>
                </div>
            </CardHeader>
            {newGroupToken && (
                <CardContent>
                    <div className="rounded-md border border-amber-500/50 bg-amber-50/30 p-3 dark:bg-amber-950/30">
                        <p className="mb-2 text-xs font-semibold text-amber-700 dark:text-amber-300">
                            Group token created — copy it now. It will not be shown again after this message.
                        </p>
                        <code className="block max-w-full break-all rounded bg-muted px-2 py-1 font-mono text-[10px]">
                            {newGroupToken.full_access_token}
                        </code>
                        <div className="mt-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => navigator.clipboard?.writeText(newGroupToken.full_access_token)}
                            >
                                Copy Full-Access Group Token
                            </Button>
                        </div>
                    </div>
                </CardContent>
            )}
            <CardContent>
                <h3 className="text-sm font-medium mb-2">Databases in this group:</h3>
                <ScrollArea className="h-[700px]">
                    <div className="space-y-4">
                        <div className="grid gap-2">
                            {group.members.map((database) => {
                                if (!database.id || !database.database_name) return null;

                                const token = getDatabaseToken(database.id);

                                return (
                                    <div key={database.id} className="flex items-center gap-2 rounded-md border p-3">
                                        <DatabaseIcon className="h-4 w-4 text-primary" />
                                        <div>
                                            <p className="font-medium">{database.database_name}</p>
                                            <p className="text-xs text-muted-foreground">ID: {database.id}</p>
                                        </div>
                                        <Badge variant="outline" className="ml-auto border-green-400 dark:border-green-600 text-green-400 dark:text-green-600">
                                            Active
                                        </Badge>
                                        {can('delete-databases') && <ButtonDelete handleDelete={() => handleDeleteDatabase(database.id)} text="Delete Database" />}
                                        {token && (
                                            <>
                                                <ButtonCopyReadOnlyToken token={token} />
                                                <ButtonCopyFullAccessToken token={token} />
                                                <ButtonOpenDatabaseStudio databaseName={database.database_name} />
                                            </>
                                        )}
                                    </div>
                                )
                            })}
                        </div>

                        {group.members_count === 0 && <p className="text-sm text-muted-foreground">No databases in this group.</p>}
                    </div>
                </ScrollArea>
            </CardContent>
        </Card>
    )
}
