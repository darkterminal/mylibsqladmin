import ButtonDelete from "@/components/button-actions/action-delete"
import ButtonRestore from "@/components/button-actions/action-restore"
import { DatabaseRow, databaseColumns } from "@/components/databases/database-table"
import { DatabasesPage } from "@/components/databases/databases-page"
import { Button } from "@/components/ui/button"
import { LaravelPagination } from "@/components/ui/data-table/data-table"
import { apiFetch } from "@/lib/api"
import { usePermission } from "@/lib/auth"
import { BreadcrumbItem, SharedData } from "@/types"
import { router, usePage } from "@inertiajs/react"
import { ArrowLeft } from "lucide-react"
import { useCallback } from "react"
import { toast } from "sonner"

type Databases = DatabaseRow

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

    const notifyTeamDatabasesChanged = useCallback(async () => {
        const teamId = localStorage.getItem('currentTeamId');

        if (teamId) {
            await apiFetch(route('api.teams.databases', Number(teamId)), {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            });
        }
    }, [csrfToken])

    const handleDeleteDatabase = useCallback((database: string) => {
        toast('Are you sure you want to delete this database?', {
            description: "This action cannot be undone.",
            position: 'top-center',
            action: (
                <Button
                    variant="destructive"
                    size="sm"
                    onClick={() => {
                        router.delete(route('database.force-delete', { database }), {
                            preserveScroll: true,
                            onSuccess: () => {
                                toast.success('Database deleted successfully');
                            },
                            onFinish: async () => {
                                await notifyTeamDatabasesChanged();
                                router.visit(route('dashboard.databases'));
                            }
                        });
                    }}
                >
                    Delete
                </Button>
            )
        })
    }, [notifyTeamDatabasesChanged])

    const handleRestoreDatabase = useCallback((database: string) => {
        toast('Are you sure you want to restore this database?', {
            description: "This action cannot be undone.",
            position: 'top-center',
            action: (
                <Button
                    variant="secondary"
                    size="sm"
                    onClick={() => {
                        router.post(route('database.restore'), { name: database }, {
                            preserveScroll: true,
                            onSuccess: () => {
                                toast.success('Database restore successfully');
                            },
                            onFinish: async () => {
                                await notifyTeamDatabasesChanged();
                                router.visit(route('dashboard.databases'));
                            }
                        });
                    }}
                >
                    Restore
                </Button>
            )
        })
    }, [notifyTeamDatabasesChanged])

    return (
        <div className="flex items-center gap-2">
            {can('manage-databases') && (
                <>
                    <ButtonRestore handleRestore={() => handleRestoreDatabase(database.name)} text="Restore Database" />
                    <ButtonDelete handleDelete={() => handleDeleteDatabase(database.name)} text="Delete Database" />
                </>
            )}
        </div>
    )
}

export default function DashboardDatabaseArchived({
    listOfDatabaseArchives
}: {
    listOfDatabaseArchives: LaravelPagination<Databases>
}) {
    const { hasRole } = usePermission();

    const columns = databaseColumns({
        renderActions: (database) => <DatabaseActions database={database} />,
    })

    return (
        <DatabasesPage<Databases>
            breadcrumbs={breadcrumbs}
            headTitle="Databases"
            title="Databases"
            subtitle="List all your archived databases"
            listRouteName="dashboard.database-archived"
            initialData={listOfDatabaseArchives}
            columns={columns}
            headerAction={hasRole('Super Admin') ? (
                <Button variant={'default'} onClick={() => router.visit(route('dashboard.databases'))}>
                    <ArrowLeft className="h-4 w-4" />
                    Back to Databases
                </Button>
            ) : null}
        />
    )
}
