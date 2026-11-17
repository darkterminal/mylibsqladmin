import ButtonCopyFullAccessToken from "@/components/button-actions/action-copy-full-access-token"
import ButtonCopyReadOnlyToken from "@/components/button-actions/action-copy-read-only-token"
import ButtonDelete from "@/components/button-actions/action-delete"
import ButtonOpenDatabaseStudio from "@/components/button-actions/action-open-database-studio"
import { ButtonSDks } from "@/components/button-actions/action-sdks"
import { DatabaseRow, databaseColumns } from "@/components/databases/database-table"
import { DatabasesPage } from "@/components/databases/databases-page"
import { AppTooltip } from "@/components/app-tooltip"
import { ModalGrantUserDatabase } from "@/components/modals/modal-grant-user-database"
import { Button } from "@/components/ui/button"
import { LaravelPagination } from "@/components/ui/data-table/data-table"
import { apiFetch } from "@/lib/api"
import { usePermission } from "@/lib/auth"
import { AllowedUser, BreadcrumbItem, SharedData } from "@/types"
import { router, usePage } from "@inertiajs/react"
import { Trash2Icon, UserPen } from "lucide-react"
import { useCallback } from "react"
import { toast } from "sonner"
import { getCurrentTeamId } from "@/lib/current-team"

interface Databases extends DatabaseRow {
    granted_users_count: number
    allUsers: AllowedUser[]
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Databases',
        href: '/dashboard/databases',
    }
];

function DatabaseActions({ database }: { database: Databases }) {
    const { can } = usePermission()
    const { csrfToken } = usePage<SharedData>().props

    const handleDeleteDatabase = useCallback((database: string) => {
        toast('Archive this database?', {
            description: 'You can restore it anytime from Databases Archived.',
            position: 'top-center',
            action: (
                <Button
                    variant="destructive"
                    size="sm"
                    onClick={() => {
                        router.delete(route('database.delete', { database }), {
                            preserveScroll: true,
                            onSuccess: (page) => {
                                const error = page?.props?.flash?.error;
                                if (error) {
                                    toast.error(String(error), { position: 'top-center' });
                                } else {
                                    toast.success('Database archived');
                                }
                            },
                            onFinish: async () => {
                                const teamId = getCurrentTeamId();

                                if (teamId) {
                                    await apiFetch(route('api.teams.databases', Number(teamId)), {
                                        method: 'GET',
                                        headers: {
                                            'X-CSRF-TOKEN': csrfToken
                                        }
                                    });
                                }

                                // the delete redirect already re-rendered this page; only
                                // the shared team props (sidebar list) need to catch up,
                                // so partial-reload them instead of a full second visit
                                router.reload({ only: ['databases', 'groups'] });
                            }
                        });
                    }}
                >
                    Archive
                </Button>
            )
        })
    }, [csrfToken])

    return (
        <div className="flex items-center gap-2">
            <ButtonOpenDatabaseStudio databaseName={database.name} text="Studio" />
            <ButtonSDks />
            {database.token && (
                <>
                    <ButtonCopyFullAccessToken token={database.token.full_access_token} />
                    <ButtonCopyReadOnlyToken token={database.token.read_only_token} />
                </>
            )}
            {can('delete-databases') && (
                <>
                    <ModalGrantUserDatabase
                        mostUsedDatabases={[{
                            database_id: database.id,
                            database_name: database.name,
                            is_schema: database.is_schema
                        }]}
                        users={database.allUsers}>
                        <AppTooltip text="Grant User Access">
                            <Button variant={'default'} size="sm">
                                <UserPen className="h-4 w-4" />
                            </Button>
                        </AppTooltip>
                    </ModalGrantUserDatabase>
                    <ButtonDelete handleDelete={() => handleDeleteDatabase(database.name)} text="Archive Database" />
                </>
            )}
        </div>
    )
}

export default function DashboardDatabase({
    listOfDatabases
}: {
    listOfDatabases: LaravelPagination<Databases>
}) {
    const { hasRole } = usePermission();

    const columns = databaseColumns({
        showGrantedUsersCount: true,
        renderActions: (database) => <DatabaseActions database={database as Databases} />,
    })

    return (
        <DatabasesPage<Databases>
            breadcrumbs={breadcrumbs}
            headTitle="Databases"
            title="Databases"
            subtitle="List all your databases"
            listRouteName="dashboard.databases"
            initialData={listOfDatabases}
            columns={columns}
            partialOnly={['listOfDatabases']}
            headerAction={hasRole('Super Admin') ? (
                <Button variant={'default'} onClick={() => router.visit(route('dashboard.database-archived'))}>
                    <Trash2Icon className="h-4 w-4" />
                    Databases Archived
                </Button>
            ) : null}
        />
    )
}
