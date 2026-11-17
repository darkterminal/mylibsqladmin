import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select";
import { type DashboardSeriesPoint } from "@/types";
import { useState } from "react";
import { Area, AreaChart, CartesianGrid, Legend, XAxis, YAxis, Tooltip, ResponsiveContainer } from "recharts";
import { formatBytes } from "@/lib/utils";

type Metric = "queries" | "rows" | "storage";

const compactNumber = (value: number) => {
    if (value >= 1_000_000_000) return `${(value / 1_000_000_000).toFixed(1)}G`;
    if (value >= 1_000_000) return `${(value / 1_000_000).toFixed(1)}M`;
    if (value >= 1_000) return `${(value / 1_000).toFixed(1)}k`;
    return `${value}`;
};

const compactBytes = (value: number) => {
    if (value >= 1_000_000_000) return `${(value / 1_000_000_000).toFixed(1)} GB`;
    if (value >= 1_000_000) return `${(value / 1_000_000).toFixed(1)} MB`;
    if (value >= 1_000) return `${(value / 1_000).toFixed(1)} KB`;
    return `${value} B`;
};

const chartConfig = {
    queries: { label: "Queries", color: "hsl(var(--chart-1))" },
    rows_read: { label: "Rows Read", color: "hsl(var(--chart-2))" },
    rows_written: { label: "Rows Written", color: "hsl(var(--chart-3))" },
    storage_bytes: { label: "Storage", color: "hsl(var(--chart-4))" },
} as const;

function formatTick(bucket: string) {
    // 'YYYY-MM-DD HH:00' -> 'HH:00'
    return bucket.slice(11, 16);
}

function ChartTip({ active, payload, label }: {
    active?: boolean;
    payload?: Array<{ name?: string; value?: number; dataKey?: string; color?: string }>;
    label?: string;
}) {
    if (!active || !payload || payload.length === 0) return null;

    return (
        <div className="rounded-md border bg-background px-3 py-2 text-xs shadow-md">
            <div className="mb-1.5 font-medium tabular-nums">{label}</div>
            <div className="flex flex-col gap-1">
                {payload.map((entry) => (
                    <div key={String(entry.dataKey)} className="flex items-center gap-2">
                        <span
                            className="h-2 w-2 shrink-0 rounded-full"
                            style={{ background: entry.color ?? "hsl(var(--muted-foreground))" }}
                        />
                        <span className="text-muted-foreground">{entry.name}:</span>
                        <span className="ml-auto font-mono tabular-nums">
                            {entry.dataKey === "storage_bytes"
                                ? formatBytes(Number(entry.value ?? 0))
                                : Number(entry.value ?? 0).toLocaleString()}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}

/**
 * Hourly performance chart for the dashboard. One metric family at a time,
 * so units never get stacked on top of each other; series come pre-filled
 * (25 buckets) from the server.
 */
export function DatabaseStats({ series }: { series: DashboardSeriesPoint[] }) {
    const [metric, setMetric] = useState<Metric>("queries");

    return (
        <div className="flex min-w-0 flex-col gap-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-xs text-muted-foreground">Last 24 hours · hourly buckets</p>
                <Select value={metric} onValueChange={(value) => setMetric(value as Metric)}>
                    <SelectTrigger className="h-8 w-[190px] text-xs" aria-label="Chart metric">
                        <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectItem value="queries">Queries</SelectItem>
                        <SelectItem value="rows">Rows read / written</SelectItem>
                        <SelectItem value="storage">Storage (bytes)</SelectItem>
                    </SelectContent>
                </Select>
            </div>
            <div className="h-[280px] w-full">
                <ResponsiveContainer width="100%" height="100%">
                    <AreaChart data={series} margin={{ top: 8, right: 8, left: 0, bottom: 0 }} accessibilityLayer>
                        <CartesianGrid strokeDasharray="3 3" vertical={false} />
                        <XAxis
                            dataKey="t"
                            tickLine={false}
                            axisLine={false}
                            minTickGap={32}
                            tickFormatter={formatTick}
                            fontSize={11}
                        />
                        <YAxis
                            tickLine={false}
                            axisLine={false}
                            width={48}
                            fontSize={11}
                            tickFormatter={metric === "storage" ? compactBytes : compactNumber}
                        />
                        <Tooltip content={<ChartTip />} cursor={{ strokeDasharray: "4 4", stroke: "hsl(var(--muted-foreground))" }} />
                        <Legend iconType="circle" iconSize={8} wrapperStyle={{ fontSize: 12 }} />
                        {metric === "queries" && (
                            <Area
                                type="monotone"
                                dataKey="queries"
                                name="Queries"
                                stroke={chartConfig.queries.color}
                                fill={chartConfig.queries.color}
                                fillOpacity={0.28}
                                strokeWidth={2}
                            />
                        )}
                        {metric === "rows" && (
                            <Area
                                type="monotone"
                                dataKey="rows_read"
                                name="Rows Read"
                                stroke={chartConfig.rows_read.color}
                                fill={chartConfig.rows_read.color}
                                fillOpacity={0.28}
                                strokeWidth={2}
                            />
                        )}
                        {metric === "rows" && (
                            <Area
                                type="monotone"
                                dataKey="rows_written"
                                name="Rows Written"
                                stroke={chartConfig.rows_written.color}
                                fill={chartConfig.rows_written.color}
                                fillOpacity={0.28}
                                strokeWidth={2}
                            />
                        )}
                        {metric === "storage" && (
                            <Area
                                type="monotone"
                                dataKey="storage_bytes"
                                name="Storage"
                                stroke={chartConfig.storage_bytes.color}
                                fill={chartConfig.storage_bytes.color}
                                fillOpacity={0.28}
                                strokeWidth={2}
                            />
                        )}
                    </AreaChart>
                </ResponsiveContainer>
            </div>
        </div>
    );
}
