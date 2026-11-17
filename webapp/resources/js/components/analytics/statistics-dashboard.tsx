import { SlowestQueriesTable } from "@/components/analytics/slowest-queries-table"
import { TopQueriesTable } from "@/components/analytics/top-queries-table"
import { DatabaseStats } from "@/components/charts/database-stats"
import { Button } from "@/components/ui/button"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { cn, databaseGroupType, formatBytes } from "@/lib/utils"
import {
    type DashboardMetricWindow,
    type DashboardMetrics,
    type DashboardSeriesPoint,
    type MostUsedDatabaseProps,
    type RecentActivityItem,
} from "@/types"
import { Link, router } from "@inertiajs/react"
import {
    Activity,
    ArrowDownToLine,
    ArrowUpFromLine,
    Cylinder,
    Database,
    GitBranch,
    HardDrive,
    Info,
    KeyRound,
    LogIn,
    LogOut,
    Plus,
    RefreshCw,
    SquareTerminal,
    Trash2,
    TrendingDown,
    TrendingUp,
    Zap,
} from "lucide-react"
import { useState } from "react"

const emptyWindow: DashboardMetricWindow = { query_count: 0, rows_read: 0, rows_written: 0 }
const zeroWindow = (series: DashboardSeriesPoint[]): DashboardSeriesPoint[] =>
    series.map((point) => ({ ...point, queries: 0, rows_read: 0, rows_written: 0, storage_bytes: 0 }))

function Delta({ current, previous }: { current: number; previous: number }) {
    if (previous <= 0) {
        return current > 0
            ? <span className="font-medium text-emerald-600 dark:text-emerald-400">new</span>
            : <span className="text-muted-foreground">—</span>
    }

    const pct = Math.round(((current - previous) / previous) * 100)

    if (pct === 0) {
        return <span className="text-muted-foreground">±0% vs prev 24h</span>
    }

    const up = pct > 0
    const Icon = up ? TrendingUp : TrendingDown

    return (
        <span className="inline-flex items-center gap-1 text-muted-foreground">
            <Icon className={cn("h-3.5 w-3.5", up ? "text-emerald-500" : "text-rose-500")} aria-hidden="true" />
            <span className="tabular-nums">{Math.abs(pct)}%</span> vs prev 24h
        </span>
    )
}

function StatCard({
    icon: Icon,
    label,
    value,
    sub,
}: {
    icon: typeof Activity
    label: string
    value: string
    sub: React.ReactNode
}) {
    return (
        <Card className="gap-0">
            <CardHeader className="pb-2">
                <CardTitle className="flex items-center text-sm font-medium text-muted-foreground">
                    <Icon className="mr-2 h-4 w-4" aria-hidden="true" />
                    {label}
                </CardTitle>
            </CardHeader>
            <CardContent>
                <div className="text-2xl font-bold tracking-tight tabular-nums">{value}</div>
                <div className="mt-1 text-xs">{sub}</div>
            </CardContent>
        </Card>
    )
}

function activityIcon(type: string) {
    const t = type.toLowerCase()
    if (t.includes("logout")) return LogOut
    if (t.includes("login")) return LogIn
    if (t.includes("token")) return KeyRound
    if (t.includes("studio")) return SquareTerminal
    if (t.includes("delete") || t.includes("force") || t.includes("archive")) return Trash2
    if (t.includes("database") || t.includes("group")) return Database
    return Activity
}

export default function StatisticsDashboard({
    databaseMetrics,
    mostUsedDatabases = [],
    recentActivity = [],
}: {
    databaseMetrics: DashboardMetrics
    mostUsedDatabases?: MostUsedDatabaseProps[]
    recentActivity?: RecentActivityItem[]
}) {
    const [scope, setScope] = useState<"all" | number>("all")
    const [refreshing, setRefreshing] = useState(false)

    const refresh = () => {
        router.reload({
            only: ["databaseMetrics", "mostUsedDatabases", "recentActivity"],
            onStart: () => setRefreshing(true),
            onFinish: () => setRefreshing(false),
        })
    }

    // Honest empty state: no visible databases at all.
    if (mostUsedDatabases.length === 0) {
        return (
            <div className="container mx-auto py-2">
                <div className="flex h-96 flex-col items-center justify-center gap-3 text-center">
                    <div className="rounded-full bg-muted p-4">
                        <Database className="h-8 w-8 text-muted-foreground" aria-hidden="true" />
                    </div>
                    <h2 className="text-xl font-semibold">No databases yet</h2>
                    <p className="max-w-md text-sm text-muted-foreground">
                        Create your first libSQL database — usage metrics, top queries and slow-query
                        analysis will appear here automatically once it starts serving traffic.
                    </p>
                    <Button asChild>
                        <Link href="/dashboard/databases">
                            <Plus className="h-4 w-4" aria-hidden="true" />
                            Create your first database
                        </Link>
                    </Button>
                </div>
            </div>
        )
    }

    const scopeAll = scope === "all"
    const selectedDb = scopeAll ? undefined : databaseMetrics.databases.find((db) => db.database_id === scope)
    const selectedName = scopeAll
        ? "All databases"
        : mostUsedDatabases.find((db) => db.database_id === scope)?.database_name ?? "Unknown database"

    const currentWindow: DashboardMetricWindow = scopeAll ? databaseMetrics.totals : selectedDb ?? emptyWindow
    const previous = scopeAll ? databaseMetrics.previous : selectedDb?.previous ?? emptyWindow
    const storageBytes = scopeAll ? databaseMetrics.totals.storage_bytes : selectedDb?.storage_bytes ?? null
    const series = scopeAll ? databaseMetrics.series : selectedDb?.series ?? zeroWindow(databaseMetrics.series)
    const topQueries = scopeAll ? databaseMetrics.top_queries : selectedDb?.top_queries ?? []
    const slowestQueries = scopeAll ? databaseMetrics.slowest_queries : selectedDb?.slowest_queries ?? []
    const scopeSamples = scopeAll
        ? databaseMetrics.databases.reduce((count, db) => count + db.samples, 0)
        : selectedDb?.samples ?? 0
    const hasAnyMetrics = databaseMetrics.databases.some((db) => db.samples > 0)

    const updatedAt = (() => {
        const parsed = new Date(databaseMetrics.generated_at)
        return Number.isNaN(parsed.getTime())
            ? null
            : parsed.toLocaleTimeString([], { hour: "2-digit", minute: "2-digit" })
    })()

    const { standaloneDatabases, parentDatabases, childDatabases } = databaseGroupType(mostUsedDatabases)

    return (
        <div className="container mx-auto flex flex-col gap-5 py-2">
            {/* Header */}
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight">Overview</h1>
                    <p className="text-sm text-muted-foreground">
                        {mostUsedDatabases.length} {mostUsedDatabases.length === 1 ? "database" : "databases"} visible
                        {" · "}last 24 hours
                        {updatedAt ? <>{" · "}updated {updatedAt}</> : null}
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-2">
                    <Select
                        value={String(scope)}
                        onValueChange={(value) => setScope(value === "all" ? "all" : Number(value))}
                    >
                        <SelectTrigger className="h-9 w-[220px]" aria-label="Metrics scope">
                            <SelectValue placeholder="All databases" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">
                                <Cylinder className="h-3.5 w-3.5" /> All databases
                            </SelectItem>
                            {standaloneDatabases
                                .slice()
                                .sort((a, b) => a.database_id - b.database_id)
                                .map((db) => (
                                    <SelectItem key={db.database_id} value={String(db.database_id)}>
                                        <Cylinder className="h-3.5 w-3.5" /> {db.database_name}
                                    </SelectItem>
                                ))}
                            {parentDatabases.map((db) => (
                                <div key={db.database_id}>
                                    <SelectItem value={String(db.database_id)}>
                                        <Database className="h-3.5 w-3.5" /> {db.database_name}
                                    </SelectItem>
                                    {childDatabases.get(db.database_name)?.map((child) => (
                                        <SelectItem key={child.database_id} value={String(child.database_id)}>
                                            <GitBranch className="h-3.5 w-3.5" /> {child.database_name}
                                        </SelectItem>
                                    ))}
                                </div>
                            ))}
                        </SelectContent>
                    </Select>
                    <Button variant="outline" size="sm" onClick={refresh} disabled={refreshing} className="h-9">
                        <RefreshCw className={cn("h-4 w-4", refreshing && "animate-spin")} aria-hidden="true" />
                        Refresh
                    </Button>
                    <Button variant="outline" size="sm" asChild className="h-9">
                        <Link href="/dashboard/databases">
                            <Database className="h-4 w-4" aria-hidden="true" />
                            Databases
                        </Link>
                    </Button>
                    <Button variant="outline" size="sm" asChild className="h-9">
                        <Link href="/dashboard/tokens">
                            <KeyRound className="h-4 w-4" aria-hidden="true" />
                            Tokens
                        </Link>
                    </Button>
                </div>
            </div>

            {/* Zero-metrics banner — the page still renders, it just says so */}
            {!hasAnyMetrics && (
                <div className="flex items-start gap-3 rounded-lg border border-dashed bg-muted/40 px-4 py-3 text-sm">
                    <Info className="mt-0.5 h-4 w-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                    <div>
                        <p className="font-medium">No metrics recorded in the last 24 hours</p>
                        <p className="text-muted-foreground">
                            Numbers will appear here once your databases start serving traffic.
                            Storage reflects the latest snapshot per database.
                        </p>
                    </div>
                </div>
            )}

            {/* KPI strip — scope-aware */}
            <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                <StatCard
                    icon={Zap}
                    label="Queries (24h)"
                    value={currentWindow.query_count.toLocaleString()}
                    sub={<Delta current={currentWindow.query_count} previous={previous.query_count} />}
                />
                <StatCard
                    icon={ArrowDownToLine}
                    label="Rows Read (24h)"
                    value={currentWindow.rows_read.toLocaleString()}
                    sub={<Delta current={currentWindow.rows_read} previous={previous.rows_read} />}
                />
                <StatCard
                    icon={ArrowUpFromLine}
                    label="Rows Written (24h)"
                    value={currentWindow.rows_written.toLocaleString()}
                    sub={<Delta current={currentWindow.rows_written} previous={previous.rows_written} />}
                />
                <StatCard
                    icon={HardDrive}
                    label="Storage Used"
                    value={storageBytes === null ? "—" : formatBytes(storageBytes)}
                    sub={
                        scopeSamples === 0
                            ? <span className="text-muted-foreground">no samples yet</span>
                            : <span className="text-muted-foreground">latest snapshot{scopeAll ? " · summed" : ""}</span>
                    }
                />
            </div>

            {/* Chart + recent activity */}
            <div className="grid gap-4 lg:grid-cols-3">
                <Card className="min-w-0 lg:col-span-2">
                    <CardHeader>
                        <CardTitle>Database Performance</CardTitle>
                        <CardDescription>{selectedName} — hourly activity, last 24 hours</CardDescription>
                    </CardHeader>
                    <CardContent className="flex min-w-0 flex-col gap-3">
                        {scopeSamples === 0 && (
                            <p className="rounded-md bg-muted/50 px-3 py-2 text-xs text-muted-foreground">
                                No samples for {scopeAll ? "any database" : selectedName} in this window — the chart is flat by design.
                            </p>
                        )}
                        <DatabaseStats series={series} />
                    </CardContent>
                </Card>

                <Card className="flex min-w-0 flex-col">
                    <CardHeader>
                        <CardTitle>Recent Activity</CardTitle>
                        <CardDescription>Latest events in this workspace</CardDescription>
                    </CardHeader>
                    <CardContent className="flex-1">
                        {recentActivity.length === 0 ? (
                            <div className="flex h-full min-h-32 flex-col items-center justify-center gap-2 text-center">
                                <Activity className="h-6 w-6 text-muted-foreground" aria-hidden="true" />
                                <p className="text-sm font-medium">No recent activity yet</p>
                                <p className="max-w-[240px] text-xs text-muted-foreground">
                                    Logins, database and token actions will show up here.
                                </p>
                            </div>
                        ) : (
                            <ul className="flex max-h-[360px] flex-col overflow-y-auto">
                                {recentActivity.map((item) => {
                                    const Icon = activityIcon(item.type)

                                    return (
                                        <li key={item.id} className="flex items-start gap-3 rounded-md px-2 py-2 hover:bg-muted/60">
                                            <span className="mt-0.5 shrink-0 rounded-md bg-muted p-1.5">
                                                <Icon className="h-3.5 w-3.5 text-muted-foreground" aria-hidden="true" />
                                            </span>
                                            <span className="min-w-0">
                                                <span className="block text-sm leading-snug">{item.description}</span>
                                                <span className="mt-0.5 block text-xs text-muted-foreground">
                                                    {item.user_name ?? "System"}
                                                    {item.time_ago ? <>{" · "}{item.time_ago}</> : null}
                                                </span>
                                            </span>
                                        </li>
                                    )
                                })}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>

            {/* Query tables — merged across the whole selection window */}
            <Tabs defaultValue="top-queries">
                <TabsList className="grid w-full max-w-md grid-cols-2">
                    <TabsTrigger value="top-queries">Top Queries</TabsTrigger>
                    <TabsTrigger value="slowest-queries">Slowest Queries</TabsTrigger>
                </TabsList>
                <TabsContent value="top-queries">
                    <Card>
                        <CardHeader>
                            <CardTitle>Top Queries</CardTitle>
                            <CardDescription>
                                Most-read queries for {selectedName} in the last 24 hours, merged across snapshots
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <TopQueriesTable queries={topQueries} />
                        </CardContent>
                    </Card>
                </TabsContent>
                <TabsContent value="slowest-queries">
                    <Card>
                        <CardHeader>
                            <CardTitle>Slowest Queries</CardTitle>
                            <CardDescription>
                                Worst observed execution time per query for {selectedName}, last 24 hours
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <SlowestQueriesTable queries={slowestQueries} />
                        </CardContent>
                    </Card>
                </TabsContent>
            </Tabs>
        </div>
    )
}
