import ButtonDelete from "@/components/button-actions/action-delete"
import ButtonRestore from "@/components/button-actions/action-restore"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import { Column, DataTable, LaravelPagination } from "@/components/ui/data-table/data-table"
import AppLayout from "@/layouts/app-layout"
import { apiFetch } from "@/lib/api"
import { usePermission } from "@/lib/auth"
import { databaseType, formatBytes, getQuery } from "@/lib/utils"
import { BreadcrumbItem, Team } from "@/types"
import { Head, router } from "@inertiajs/react"
import { ArrowLeft, Cylinder, DatabaseIcon, File, GitBranch, Handshake, Users } from "lucide-react"
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
    }
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
                                const teamId = localStorage.getItem('currentTeamId');

                                if (teamId) {
                                    await apiFetch(route('api.teams.databases', Number(teamId)));
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
                                const teamId = localStorage.getItem('currentTeamId');

                                if (teamId) {
                                    await apiFetch(route('api.teams.databases', Number(teamId)));
                                }

                                router.visit(route('dashboard.databases'));
                            }
                        });
                    }}
                >
                    Restore
                </Button>
            )
        })
    }, [])

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

export default function DashboardDatabase({
    listOfDatabaseArchives
}: {
    listOfDatabaseArchives: LaravelPagination<Databases>
}) {
    const { hasRole } = usePermission();
    const search = getQuery('search');
    const [data, setData] = useState<LaravelPagination<Databases>>(listOfDatabaseArchives)
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
            ...listOfDatabaseArchives,
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
                        <p className="text-muted-foreground text-sm">List all your archived databases</p>
                    </div>
                    {hasRole('Super Admin') && (
                        <Button variant={'default'} onClick={() => router.visit(route('dashboard.databases'))}>
                            <ArrowLeft className="h-4 w-4" />
                            Back to Databases
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
