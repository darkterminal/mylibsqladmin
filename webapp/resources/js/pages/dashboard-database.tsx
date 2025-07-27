import { AppTooltip } from "@/components/app-tooltip"
import ButtonCopyFullAccessToken from "@/components/button-actions/action-copy-full-access-token"
import ButtonCopyReadOnlyToken from "@/components/button-actions/action-copy-read-only-token"
import ButtonDelete from "@/components/button-actions/action-delete"
import ButtonOpenDatabaseStudio from "@/components/button-actions/action-open-database-studio"
import { ButtonSDks } from "@/components/button-actions/action-sdks"
import { ModalGrantUserDatabase } from "@/components/modals/modal-grant-user-database"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Column, DataTable, LaravelPagination } from "@/components/ui/data-table/data-table"
import AppLayout from "@/layouts/app-layout"
import { apiFetch } from "@/lib/api"
import { usePermission } from "@/lib/auth"
import { databaseType, formatBytes, getQuery } from "@/lib/utils"
import { AllowedUser, BreadcrumbItem, SharedData, Team } from "@/types"
import { Head, router, usePage } from "@inertiajs/react"
import { Cylinder, DatabaseIcon, File, GitBranch, Handshake, Trash2Icon, UserPen, Users } from "lucide-react"
import { useCallback, useState } from "react"
import { toast } from "sonner"

interface Databases {
    id: number
    name: string
    is_schema: string
    owner: string
    groups: {
        id: number
        name: string
        team: Team
    }[]
    teams: Team[]
    tokenized: boolean
    token: {
        id: number
        name: string
        full_access_token: string
        read_only_token: string
        expires_at: string
    } | null
    stats: {
        rows_reads: number
        rows_written: number
        queries: number
        storage: number
    },
    allUsers: AllowedUser[]
}

const databaseColumns: Column<Databases>[] = [
    {
        key: "name",
        label: "Database",
        render: (database) => (
            <div className="flex items-center gap-2">
                <DatabaseIcon className="h-4 w-4 text-muted-foreground" />
                <span className="font-medium">{database.name}</span>
            </div>
        ),
    },
    {
        key: "is_schema",
        label: "Type",
        render: (database) => {
            const dbType = databaseType(String(database.is_schema))
            return (
                <div className="flex items-center">
                    {dbType === "schema" && (
                        <File className="mr-2 h-4 w-4 text-primary" />
                    )}
                    {dbType === "0" && (
                        <Cylinder className="mr-2 h-4 w-4 text-primary" />
                    )}
                    {dbType === database.is_schema && (
                        <GitBranch className="mr-2 h-4 w-4 text-primary" />
                    )}
                    {dbType === "schema" && (
                        <span className="text-xs text-muted-foreground">schema</span>
                    )}
                    {dbType === "0" && (
                        <span className="text-xs text-muted-foreground">standalone</span>
                    )}
                    {dbType === database.is_schema && (
                        <span className="text-xs text-muted-foreground">child of <span className="text-primary">{database.is_schema}</span></span>
                    )}
                </div>
            )
        }
    },
    {
        key: "owner",
        label: "Owner",
    },
    {
        key: "teams",
        label: "Teams",
        render: (database) => (
            <div className="flex items-center gap-2">
                <Handshake className="h-4 w-4 text-muted-foreground" />
                <div className="flex flex-wrap gap-1">
                    {database.teams.map((team) => (
                        <Badge key={team.id} variant="outline">
                            {team.name}
                        </Badge>
                    ))}
                </div>
            </div>
        ),
    },
    {
        key: "groups",
        label: "Groups",
        render: (database) => (
            <div className="flex items-center gap-2">
                <Users className="h-4 w-4 text-muted-foreground" />
                <div className="flex flex-wrap gap-1">
                    {database.groups.map((group) => (
                        <Badge key={group.id} variant="outline">
                            {group.name}
                        </Badge>
                    ))}
                </div>
            </div>
        ),
    },
    {
        key: "stats.rows_read",
        label: "Rows Read / Written",
        render: (database) => <span>{database.stats.rows_reads.toLocaleString()} / {database.stats.rows_written.toLocaleString()}</span>,
    },
    {
        key: "stats.queries",
        label: "Total Queries",
        render: (database) => <span>{database.stats.queries.toLocaleString()}</span>,
    },
    {
        key: "stats.storage",
        label: "Storage Usage",
        render: (database) => <span>{formatBytes(database.stats.storage)}</span>,
    },
    {
        key: "tokenized",
        label: "Status",
        render: (database) => (
            <Badge variant={database.tokenized ? "default" : "secondary"}>
                {database.tokenized ? "Tokenized" : "Not Tokenized"}
            </Badge>
        ),
    },
    {
        key: "#",
        label: "#",
        render: (database) => <DatabaseActions database={database} />,
    }
]

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
        toast('Are you sure you want to delete this database?', {
            description: "This action cannot be undone.",
            position: 'top-center',
            action: (
                <Button
                    variant="destructive"
                    size="sm"
                    onClick={() => {
                        router.delete(route('database.delete', { database }), {
                            preserveScroll: true,
                            onSuccess: () => {
                                toast.success('Database deleted successfully');
                            },
                            onFinish: async () => {
                                const teamId = localStorage.getItem('currentTeamId');

                                if (teamId) {
                                    await apiFetch(route('api.teams.databases', Number(teamId)), {
                                        method: 'GET',
                                        headers: {
                                            'X-CSRF-TOKEN': csrfToken
                                        }
                                    });
                                }

                                router.visit(route('dashboard.databases'));
                            }
                        });
                    }}
                >
                    Delete
                </Button>
            )
        })
    }, [])

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
                    <ButtonDelete handleDelete={() => handleDeleteDatabase(database.name)} text="Delete Database" />
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
    const search = getQuery('search');
    const [data, setData] = useState<LaravelPagination<Databases>>(listOfDatabases)
    const [isLoading, setIsLoading] = useState(false)
    const [searchQuery, setSearchQuery] = useState(search)

    const fetchData = async (page: number, search?: string) => {
        setIsLoading(true)

        if (search) {
            router.visit(`${route('dashboard.databases')}?search=${search}&page=${page}`)
        } else {
            router.visit(route('dashboard.databases'))
        }

        setData({
            ...listOfDatabases,
            current_page: page,
        })

        setIsLoading(false)
    }

    const handlePageChange = (page: number) => {
        fetchData(page, searchQuery)
    }

    const handleSearch = (query: string) => {
        setSearchQuery(query)
        fetchData(1, query)
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Databases" />
            <div className="container mx-auto py-8">
                <div className="flex items-center justify-between">
                    <div className="mb-6">
                        <h1 className="text-2xl font-bold">Databases</h1>
                        <p className="text-muted-foreground text-sm">List all your databases</p>
                    </div>
                    {hasRole('Super Admin') && (
                        <Button variant={'default'} onClick={() => router.visit(route('dashboard.database-archived'))}>
                            <Trash2Icon className="h-4 w-4" />
                            Databases Archived
                        </Button>
                    )}
                </div>
                <DataTable
                    data={data}
                    columns={databaseColumns}
                    onPageChange={handlePageChange}
                    onSearch={handleSearch}
                    searchPlaceholder="Search databases..."
                    isLoading={isLoading}
                />
            </div>
        </AppLayout>
    )
}
