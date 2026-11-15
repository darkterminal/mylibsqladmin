import { Badge } from "@/components/ui/badge"
import { Column } from "@/components/ui/data-table/data-table"
import { databaseType, formatBytes } from "@/lib/utils"
import { Team } from "@/types"
import { Cylinder, DatabaseIcon, File, GitBranch, Handshake, Users } from "lucide-react"

/**
 * Shared shape for database rows across the active and archived lists.
 */
export interface DatabaseRow {
    id: number
    name: string
    is_schema: string
    deleted_at?: string | null
    granted_users_count?: number
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

function typeCell(database: DatabaseRow) {
    const dbType = databaseType(String(database.is_schema))
    const isStandalone = dbType === "standalone"
    const isSchema = dbType === "schema"
    const isChild = !isStandalone && !isSchema // databaseType returns the parent name for children

    return (
        <div className="flex items-center">
            {isSchema && <File className="mr-2 h-4 w-4 text-primary" />}
            {isStandalone && <Cylinder className="mr-2 h-4 w-4 text-primary" />}
            {isChild && <GitBranch className="mr-2 h-4 w-4 text-primary" />}
            {isSchema && <span className="text-xs text-muted-foreground">schema</span>}
            {isStandalone && <span className="text-xs text-muted-foreground">standalone</span>}
            {isChild && (
                <span className="text-xs text-muted-foreground">
                    child of <span className="text-primary">{database.is_schema}</span>
                </span>
            )}
        </div>
    )
}

export function databaseColumns({
    renderActions,
    showGrantedUsersCount = false,
}: {
    renderActions: (row: DatabaseRow) => React.ReactNode
    showGrantedUsersCount?: boolean
}): Column<DatabaseRow>[] {
    const columns: Column<DatabaseRow>[] = [
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
            render: typeCell,
        },
        ...(showGrantedUsersCount
            ? [{
                key: "granted_users_count",
                label: "Granted Users",
                render: (database: DatabaseRow) => (
                    <span className="font-medium">{database.granted_users_count ?? 0} users</span>
                ),
            } as Column<DatabaseRow>]
            : []),
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
            render: (database) => (
                <span>
                    {database.stats.rows_reads.toLocaleString()} / {database.stats.rows_written.toLocaleString()}
                </span>
            ),
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
            render: renderActions,
        },
    ]

    return columns
}
