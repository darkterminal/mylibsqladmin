import { Badge } from "@/components/ui/badge"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { triggerEvent, useCustomEvent } from "@/hooks/use-custom-event"
import { DatabaseInGroupProps, GroupDatabaseProps, UserDatabaseTokenProps } from "@/types"
import { router } from "@inertiajs/react"
import { DatabaseIcon, KeyIcon, PlusCircleIcon, Server } from "lucide-react"
import { useCallback } from "react"
import { toast } from "sonner"
import { AppTooltip } from "./app-tooltip"
import ButtonCopyFullAccessToken from "./button-actions/action-copy-full-access-token"
import ButtonCopyReadOnlyToken from "./button-actions/action-copy-read-only-token"
import ButtonDeleteGroup from "./button-actions/action-delete-group"
import ButtonOpenDatabaseStudio from "./button-actions/action-open-database-studio"
import ModalAddDatabaseToGroup from "./modals/modal-add-database-to-group"
import { ModalCreateGroupToken } from "./modals/modal-create-group-token"
import { ModalCreateToken } from "./modals/modal-create-token"
import { Button } from "./ui/button"

export default function GroupDetail({
    group,
    availableDatabases
}: {
    group: GroupDatabaseProps
    availableDatabases: DatabaseInGroupProps[]
}) {

    if (!group) return null

    const getDatabaseToken = (databaseId: number) => group.database_tokens.find(token => token.database_id === databaseId)

    const deleteGroup = useCallback(() => {
        toast('Are you sure you want to delete this group?', {
            description: "This action cannot be undone.",
            duration: 7000,
            position: "top-center",
            style: {
                cursor: "pointer",
            },
            action: (
                <Button
                    variant="destructive"
                    size="sm"
                    onClick={() => {
                        router.delete(route('group.delete', { groupId: group.id }), {
                            preserveScroll: true,
                            onSuccess: () => {
                                triggerEvent('database-group-is-deleted', { id: group.id })
                            }
                        })
                    }}
                >
                    Delete
                </Button>
            )
        })
    }, [])

    const handleOnSuccess = useCallback(() => {
        toast.success('Group token created successfully');
        router.reload();
        triggerEvent('group-token-is-created', { id: group.id });
    }, [])

    useCustomEvent('token-is-created', ({ id, newToken }: { id: number, newToken: UserDatabaseTokenProps }) => {
        toast.success('Token created successfully');
        console.log('databaseId', id);
        console.log('newToken', newToken);
    })

    return (
        <Card>
            <CardHeader className="pb-3">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-3">
                        <ButtonDeleteGroup handleDelete={deleteGroup} />
                        <CardTitle className="text-xl flex items-center"><Server className="h-5 w-5 text-muted-foreground mr-2" /> <span>{group.name}</span></CardTitle>
                        <CardDescription>
                            {group.members_count} {group.members_count === 1 ? "database" : "databases"} in this group
                        </CardDescription>
                    </div>
                    <div className="flex gap-2">
                        <AppTooltip text="Create Group Token">
                            <ModalCreateGroupToken
                                groupId={group.id}
                                onSuccess={handleOnSuccess}
                            >
                                <Button variant="default">
                                    <KeyIcon className="h-4 w-4" />
                                </Button>
                            </ModalCreateGroupToken>
                        </AppTooltip>
                        <AppTooltip text="Add Database to Group">
                            <ModalAddDatabaseToGroup groupId={group.id} databases={availableDatabases}>
                                <Button variant="default">
                                    <PlusCircleIcon className="h-4 w-4" />
                                </Button>
                            </ModalAddDatabaseToGroup>
                        </AppTooltip>
                    </div>
                </div>
            </CardHeader>
            <CardContent>
                <div className="space-y-4">
                    <h3 className="text-sm font-medium">Databases in this group:</h3>
                    <div className="grid gap-2">
                        {group.members.map((database) => {
                            const token = getDatabaseToken(database.id)!;
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
                                    {token ? (
                                        <>
                                            <ButtonCopyReadOnlyToken token={token} />
                                            <ButtonCopyFullAccessToken token={token} />
                                        </>
                                    ) : (
                                        <ModalCreateToken
                                            mostUsedDatabases={[{
                                                database_id: database.id,
                                                database_name: database.database_name,
                                                is_schema: database.is_schema
                                            }]}
                                        >
                                            <Button variant="default" size="sm">
                                                <KeyIcon className="h-4 w-4" />
                                                <span>Create Token</span>
                                            </Button>
                                        </ModalCreateToken>
                                    )}
                                    <ButtonOpenDatabaseStudio databaseName={database.database_name} />
                                </div>
                            )
                        })}
                    </div>

                    {group.members_count === 0 && <p className="text-sm text-muted-foreground">No databases in this group.</p>}
                </div>
            </CardContent>
        </Card>
    )
}
