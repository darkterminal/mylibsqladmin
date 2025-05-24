import {
    ChartContainer,
    ChartTooltip,
    ChartTooltipContent
} from "@/components/ui/chart";
import { type QueryMetrics } from "@/types";
import { Area, AreaChart, CartesianGrid, XAxis, YAxis } from "recharts";

export function DatabaseStats({ databases }: { databases: QueryMetrics[] | undefined }) {

    const chartData = databases ? databases.map(database => ({
        name: `${database.created_at}`,
        rows_read: database.rows_read_count,
        rows_written: database.rows_written_count,
        queries: database.query_count,
        storage: database.storage_bytes_used,
    })) : [
        {
            name: "T-0",
            rows_read: 0,
            rows_written: 0,
            queries: 0,
            storage: 0,
        }
    ];

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
                data={chartData.reverse()}
                margin={{
                    top: 10,
                    right: 30,
                    left: 0,
                    bottom: 0,
                }}
            >
                <CartesianGrid strokeDasharray="3 3" vertical={false} />
                <XAxis
                    dataKey="name"
                    tickLine={false}
                    axisLine={false}
                />
                <YAxis
                    tickLine={false}
                    axisLine={false}
                />
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
