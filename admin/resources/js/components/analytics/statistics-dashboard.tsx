import { SlowestQueriesTable } from "@/components/analytics/slowest-queries-table"
import { TopQueriesTable } from "@/components/analytics/top-queries-table"
import { DatabaseStats } from "@/components/charts/database-stats"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { databaseGroupType, formatBytes } from "@/lib/utils"
import { MostUsedDatabaseProps, QueryMetrics } from "@/types"
import { Calculator, Cylinder, Database, GitBranch, ReceiptText } from "lucide-react"
import React, { useEffect, useState } from "react"

export default function StatisticsDashboard({
    databasesData: databaseMetricts,
    mostUsedDatabases
}: {
    databasesData: QueryMetrics[],
    mostUsedDatabases: MostUsedDatabaseProps[]
}) {

    const [selectedDatabase, setSelectedDatabase] = useState(mostUsedDatabases.length > 0 ? mostUsedDatabases[0].database_name : "")
    const [databaseStats, setDatabaseStats] = useState<QueryMetrics[] | undefined>()
    const { standaloneDatabases, parentDatabases, childDatabases } = databaseGroupType(mostUsedDatabases)

    const currentDb = databaseMetricts.find((db) => db.name === selectedDatabase) || undefined

    useEffect(() => {
        if (!selectedDatabase) return
        setDatabaseStats(databaseMetricts.filter((db) => db.name === selectedDatabase))
    }, [databaseMetricts, selectedDatabase])

    return (
        <div className="container mx-auto py-8">
            <div className="flex flex-col gap-6">
                <div className="flex items-center justify-between">
                    <h1 className="text-3xl font-bold">Database Statistics</h1>
                    <Select value={selectedDatabase} onValueChange={setSelectedDatabase}>
                        <SelectTrigger className="w-[240px]">
                            <SelectValue placeholder="Select database" />
                        </SelectTrigger>
                        <SelectContent>
                            {standaloneDatabases.sort((a, b) => a.database_id - b.database_id).map((db) => (
                                <SelectItem key={db.database_id} value={db.database_name}>
                                    <Cylinder className="h-3 w-3" /> {db.database_name}
                                </SelectItem>
                            ))}
                            {parentDatabases.map((db) => (
                                <React.Fragment key={db.database_id}>
                                    <SelectItem key={db.database_id} value={db.database_name}>
                                        <Database className="h-3 w-3" /> {db.database_name}
                                    </SelectItem>
                                    {childDatabases.get(db.database_name)?.map((db) => (
                                        <SelectItem key={db.database_id} value={db.database_name}>
                                            <GitBranch className="h-3 w-3" /> {db.database_name}
                                        </SelectItem>
                                    ))}
                                </React.Fragment>
                            ))}
                        </SelectContent>
                    </Select>
                </div>

                <div className="grid gap-6 md:grid-cols-3">
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium flex items-center"><ReceiptText className="mr-2 h-4 w-4" /> Rows Read/Written</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {currentDb === undefined ? (
                                <div className="text-2xl font-bold">0 / 0</div>
                            ) : (
                                <div className="text-2xl font-bold">{currentDb.rows_read_count.toLocaleString()} / {currentDb.rows_written_count.toLocaleString()}</div>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium flex items-center"><Calculator className="mr-2 h-4 w-4" /> Query Count</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {currentDb === undefined ? (
                                <div className="text-2xl font-bold">0 / 0</div>
                            ) : (
                                <div className="text-2xl font-bold">{currentDb.query_count.toLocaleString()}</div>
                            )}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium flex items-center"><Database className="mr-2 h-4 w-4" /> Storage Used</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {currentDb === undefined ? (
                                <div className="text-2xl font-bold">0 / 0</div>
                            ) : (
                                <div className="text-2xl font-bold">{formatBytes(currentDb.storage_bytes_used)}</div>
                            )}
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Database Performance Metrics</CardTitle>
                        <CardDescription>Visualization of key database metrics</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <DatabaseStats databases={databaseStats} />
                    </CardContent>
                </Card>

                <Tabs defaultValue="top-queries">
                    <TabsList className="grid w-full grid-cols-2">
                        <TabsTrigger value="top-queries">Top Queries</TabsTrigger>
                        <TabsTrigger value="slowest-queries">Slowest Queries</TabsTrigger>
                    </TabsList>
                    <TabsContent value="top-queries">
                        <Card>
                            <CardHeader>
                                <CardTitle>Top Queries</CardTitle>
                                <CardDescription>Queries with the highest usage</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <TopQueriesTable queries={currentDb === undefined ? [] : currentDb.top_queries} />
                            </CardContent>
                        </Card>
                    </TabsContent>
                    <TabsContent value="slowest-queries">
                        <Card>
                            <CardHeader>
                                <CardTitle>Slowest Queries</CardTitle>
                                <CardDescription>Queries with the longest execution time</CardDescription>
                            </CardHeader>
                            <CardContent>
                                <SlowestQueriesTable queries={currentDb === undefined ? [] : currentDb.slowest_queries} />
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </div>
    )
}
