"use client"

import { SlowestQueriesTable } from "@/components/analytics/slowest-queries-table"
import { TopQueriesTable } from "@/components/analytics/top-queries-table"
import { DatabaseStats } from "@/components/charts/database-stats"
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from "@/components/ui/card"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { formatBytes } from "@/lib/utils"
import { Calculator, Database, ReceiptText } from "lucide-react"
import { useState } from "react"

const databasesData = [
    {
        id: "c43f2546-9ab9-4060-a355-7667e7b88031",
        name: "testdb",
        rows_read_count: 347,
        rows_written_count: 5,
        storage_bytes_used: 16384,
        query_count: 340,
        elapsed_ms: 302.948,
        top_queries: [
            { rows_written: 0, rows_read: 1, query: "BEGIN IMMEDIATE" },
            { rows_written: 0, rows_read: 1, query: "COMMIT" },
            { rows_written: 0, rows_read: 1, query: "PRAGMA database_list;" },
            { rows_written: 0, rows_read: 1, query: 'SELECT * FROM "main"."users" LIMIT 50 OFFSET 0;' },
            { rows_written: 0, rows_read: 1, query: 'SELECT * FROM "main".sqlite_schema;' },
            { rows_written: 0, rows_read: 2, query: "SELECT * FROM \"main\".sqlite_schema WHERE tbl_name = 'users';" },
            { rows_written: 0, rows_read: 2, query: 'SELECT * FROM "main".sqlite_schema;' },
            { rows_written: 0, rows_read: 3, query: 'SELECT * FROM "main".sqlite_schema;' },
            {
                rows_written: 2,
                rows_read: 1,
                query:
                    "create table post (\n id integer primary key,\n user_id integer,\n\n foreign key (user_id) references users (id)\n);",
            },
            { rows_written: 3, rows_read: 1, query: "create table users (\n id int primary key,\n name text\n);" },
        ],
        slowest_queries: [
            { elapsed_ms: 0, query: "COMMIT", rows_written: 0, rows_read: 1 },
            { elapsed_ms: 0, query: "PRAGMA database_list;", rows_written: 0, rows_read: 1 },
            { elapsed_ms: 0, query: 'SELECT * FROM "main"."users" LIMIT 50 OFFSET 0;', rows_written: 0, rows_read: 1 },
            {
                elapsed_ms: 0,
                query: "SELECT * FROM \"main\".sqlite_schema WHERE tbl_name = 'users';",
                rows_written: 0,
                rows_read: 2,
            },
            { elapsed_ms: 0, query: 'SELECT * FROM "main".sqlite_schema;', rows_written: 0, rows_read: 1 },
            { elapsed_ms: 0, query: 'SELECT * FROM "main".sqlite_schema;', rows_written: 0, rows_read: 2 },
            { elapsed_ms: 0, query: 'SELECT * FROM "main".sqlite_schema;', rows_written: 0, rows_read: 3 },
            { elapsed_ms: 1, query: "BEGIN IMMEDIATE", rows_written: 0, rows_read: 1 },
            {
                elapsed_ms: 2,
                query:
                    "create table post (\n id integer primary key,\n user_id integer,\n\n foreign key (user_id) references users (id)\n);",
                rows_written: 2,
                rows_read: 1,
            },
            {
                elapsed_ms: 281,
                query: "create table users (\n id int primary key,\n name text\n);",
                rows_written: 3,
                rows_read: 1,
            },
        ],
    },
    {
        "id": "784ebfdd-4f8a-4571-b470-6316ff0b47e6",
        "name": "anotherdb",
        "rows_read_count": 56,
        "rows_written_count": 0,
        "storage_bytes_used": 4096,
        "write_requests_delegated": 0,
        "replication_index": 0,
        "top_queries": [
            {
                "rows_written": 0,
                "rows_read": 1,
                "query": "BEGIN IMMEDIATE"
            },
            {
                "rows_written": 0,
                "rows_read": 1,
                "query": "COMMIT"
            },
            {
                "rows_written": 0,
                "rows_read": 1,
                "query": "PRAGMA database_list;"
            },
            {
                "rows_written": 0,
                "rows_read": 1,
                "query": "SELECT * FROM \"main\".sqlite_schema;"
            }
        ],
        "slowest_queries": [
            {
                "elapsed_ms": 0,
                "query": "BEGIN IMMEDIATE",
                "rows_written": 0,
                "rows_read": 1
            },
            {
                "elapsed_ms": 0,
                "query": "COMMIT",
                "rows_written": 0,
                "rows_read": 1
            },
            {
                "elapsed_ms": 0,
                "query": "PRAGMA database_list;",
                "rows_written": 0,
                "rows_read": 1
            },
            {
                "elapsed_ms": 0,
                "query": "SELECT * FROM \"main\".sqlite_schema;",
                "rows_written": 0,
                "rows_read": 1
            }
        ],
        "embedded_replica_frames_replicated": 0,
        "query_count": 56,
        "elapsed_ms": 2.571,
        "queries": {
            "id": "c8371105-2a2d-411e-ae75-011514a36414",
            "created_at": 1741385465,
            "count": 8,
            "stats": [
                {
                    "query": "COMMIT",
                    "elapsed_ms": 0.054,
                    "count": 2,
                    "rows_written": 0,
                    "rows_read": 2
                },
                {
                    "query": "BEGIN IMMEDIATE",
                    "elapsed_ms": 0.194,
                    "count": 2,
                    "rows_written": 0,
                    "rows_read": 2
                },
                {
                    "query": "PRAGMA database_list;",
                    "elapsed_ms": 0.084,
                    "count": 2,
                    "rows_written": 0,
                    "rows_read": 2
                },
                {
                    "query": "SELECT * FROM \"main\".sqlite_schema;",
                    "elapsed_ms": 0.103,
                    "count": 2,
                    "rows_written": 0,
                    "rows_read": 2
                }
            ],
            "elapsed": {
                "sum": 0.437,
                "p50": 0.037,
                "p75": 0.065,
                "p90": 0.098,
                "p95": 0.098,
                "p99": 0.098,
                "p999": 0.098
            }
        }
    },
];

export default function StatisticsDashboard() {
    const [selectedDatabase, setSelectedDatabase] = useState(databasesData[0].id)

    const currentDb = databasesData.find((db) => db.id === selectedDatabase) || databasesData[0]

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
                            {databasesData.map((db) => (
                                <SelectItem key={db.id} value={db.id}>
                                    {db.name}
                                </SelectItem>
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
                            <div className="text-2xl font-bold">{currentDb.rows_read_count.toLocaleString()} / {currentDb.rows_written_count.toLocaleString()}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium flex items-center"><Calculator className="mr-2 h-4 w-4" /> Query Count</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{currentDb.query_count.toLocaleString()}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader className="pb-2">
                            <CardTitle className="text-sm font-medium flex items-center"><Database className="mr-2 h-4 w-4" /> Storage Used</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <div className="text-2xl font-bold">{formatBytes(currentDb.storage_bytes_used)}</div>
                        </CardContent>
                    </Card>
                </div>

                <Card>
                    <CardHeader>
                        <CardTitle>Database Performance Metrics</CardTitle>
                        <CardDescription>Visualization of key database metrics</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <DatabaseStats database={currentDb} />
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
                                <TopQueriesTable queries={currentDb.top_queries} />
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
                                <SlowestQueriesTable queries={currentDb.slowest_queries} />
                            </CardContent>
                        </Card>
                    </TabsContent>
                </Tabs>
            </div>
        </div>
    )
}
