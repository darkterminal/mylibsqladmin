"use client"

import { ChartContainer, ChartTooltip, ChartTooltipContent } from "@/components/ui/chart"
import { Area, AreaChart, CartesianGrid, XAxis, YAxis } from "recharts"

// Define the database type
interface Database {
    id: string
    name: string
    rows_read_count: number
    rows_written_count: number
    storage_bytes_used: number
    query_count: number
    elapsed_ms: number
    top_queries: any[]
    slowest_queries: any[]
}

interface DatabaseStatsProps {
    database: Database
}

export function DatabaseStats({ database }: DatabaseStatsProps) {
    // Transform the data for the area chart
    // In a real app, this would be time-series data
    // For this example, we'll create mock time points
    const chartData = [
        {
            name: "T-4",
            rows_read: Math.round(database.rows_read_count * 0.4),
            rows_written: Math.round(database.rows_written_count * 0.3),
            queries: Math.round(database.query_count * 0.4),
            storage: Math.round(database.storage_bytes_used * 0.4),
        },
        {
            name: "T-3",
            rows_read: Math.round(database.rows_read_count * 0.6),
            rows_written: Math.round(database.rows_written_count * 0.5),
            queries: Math.round(database.query_count * 0.6),
            storage: Math.round(database.storage_bytes_used * 0.6),
        },
        {
            name: "T-2",
            rows_read: Math.round(database.rows_read_count * 0.7),
            rows_written: Math.round(database.rows_written_count * 0.7),
            queries: Math.round(database.query_count * 0.7),
            storage: Math.round(database.storage_bytes_used * 0.7),
        },
        {
            name: "T-1",
            rows_read: Math.round(database.rows_read_count * 0.9),
            rows_written: Math.round(database.rows_written_count * 0.8),
            queries: Math.round(database.query_count * 0.9),
            storage: Math.round(database.storage_bytes_used * 0.9),
        },
        {
            name: "Current",
            rows_read: database.rows_read_count,
            rows_written: database.rows_written_count,
            queries: database.query_count,
            storage: database.storage_bytes_used,
        },
    ]

    return (
        <ChartContainer
            config={{
                rows_read: {
                    label: "Rows Read",
                    color: "hsl(var(--chart-1))",
                },
                rows_written: {
                    label: "Rows Written",
                    color: "hsl(var(--chart-2))",
                },
                queries: {
                    label: "Queries",
                    color: "hsl(var(--chart-3))",
                },
                storage: {
                    label: "Storage (bytes)",
                    color: "hsl(var(--chart-4))",
                },
            }}
            className="h-[300px] w-full"
        >
            <AreaChart
                accessibilityLayer
                data={chartData}
                margin={{
                    top: 10,
                    right: 30,
                    left: 0,
                    bottom: 0,
                }}
            >
                <CartesianGrid strokeDasharray="3 3" vertical={false} />
                <XAxis dataKey="name" />
                <YAxis />
                <ChartTooltip content={<ChartTooltipContent />} />
                <Area
                    type="monotone"
                    dataKey="rows_read"
                    stroke="var(--color-rows_read)"
                    fill="var(--color-rows_read)"
                    fillOpacity={0.3}
                    stackId="1"
                />
                <Area
                    type="monotone"
                    dataKey="rows_written"
                    stroke="var(--color-rows_written)"
                    fill="var(--color-rows_written)"
                    fillOpacity={0.3}
                    stackId="1"
                />
                <Area
                    type="monotone"
                    dataKey="queries"
                    stroke="var(--color-queries)"
                    fill="var(--color-queries)"
                    fillOpacity={0.3}
                    stackId="1"
                />
                <Area
                    type="monotone"
                    dataKey="storage"
                    stroke="var(--color-storage)"
                    fill="var(--color-storage)"
                    fillOpacity={0.3}
                    stackId="1"
                />
            </AreaChart>
        </ChartContainer>
    )
}
