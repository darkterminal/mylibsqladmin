import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/components/ui/table"

interface Query {
    rows_written: number
    rows_read: number
    query: string
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
                        <TableHead className="w-[100px] text-right">Rows Read</TableHead>
                        <TableHead className="w-[100px] text-right">Rows Written</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {queries.length > 0 ? (
                        queries.map((query, index) => (
                            <TableRow key={index}>
                                <TableCell className="font-mono text-xs">
                                    {query.query.length > 60 ? `${query.query.substring(0, 60)}...` : query.query}
                                </TableCell>
                                <TableCell className="text-right">{query.rows_read}</TableCell>
                                <TableCell className="text-right">{query.rows_written}</TableCell>
                            </TableRow>
                        ))
                    ) : (
                        <TableRow>
                            <TableCell colSpan={3} className="text-center">
                                No data available
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>
        </div>
    )
}
