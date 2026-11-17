import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"

interface Query {
    rows_written: number
    rows_read: number
    query: string
    occurrences?: number
}

interface TopQueriesTableProps {
    queries: Query[]
}

export function TopQueriesTable({ queries }: TopQueriesTableProps) {
    return (
        <div className="overflow-x-auto">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead>Query</TableHead>
                        <TableHead className="w-[110px] text-right">Rows Read</TableHead>
                        <TableHead className="w-[110px] text-right">Rows Written</TableHead>
                        <TableHead className="w-[80px] text-right">Runs</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {queries.length > 0 ? (
                        queries.map((query, index) => (
                            <TableRow key={index}>
                                <TableCell className="font-mono text-xs" title={query.query}>
                                    {query.query.length > 60 ? `${query.query.substring(0, 60)}...` : query.query}
                                </TableCell>
                                <TableCell className="text-right tabular-nums">{query.rows_read.toLocaleString()}</TableCell>
                                <TableCell className="text-right tabular-nums">{query.rows_written.toLocaleString()}</TableCell>
                                <TableCell className="text-right tabular-nums">{query.occurrences ?? "—"}</TableCell>
                            </TableRow>
                        ))
                    ) : (
                        <TableRow>
                            <TableCell colSpan={4} className="text-center text-muted-foreground">
                                No data available
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>
        </div>
    )
}
