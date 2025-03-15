import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";

export default function TokenMangement() {
    return (
        <div className="flex flex-col flex-1">
            <h2 className="text-lg mb-2">Database Token Management</h2>
            <Table>
                <TableCaption>A list of your recent tokens.</TableCaption>
                <TableHeader>
                    <TableRow>
                        <TableHead className="w-[100px]">#</TableHead>
                        <TableHead>Database Name</TableHead>
                        <TableHead>Token Name</TableHead>
                        <TableHead>Expiration</TableHead>
                        <TableHead>Action</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    <TableRow>
                        <TableCell>1</TableCell>
                        <TableCell><code className="font-mono p-1 rounded-sm bg-accent">default</code></TableCell>
                        <TableCell>My token</TableCell>
                        <TableCell>Never</TableCell>
                        <TableCell><a href="#" className="text-primary-500 hover:underline">Revoke</a></TableCell>
                    </TableRow>
                    <TableRow>
                        <TableCell>2</TableCell>
                        <TableCell><code className="font-mono p-1 rounded-sm bg-accent">db-testing</code></TableCell>
                        <TableCell>Admin token</TableCell>
                        <TableCell>Never</TableCell>
                        <TableCell><a href="#" className="text-primary-500 hover:underline">Revoke</a></TableCell>
                    </TableRow>
                    <TableRow>
                        <TableCell>3</TableCell>
                        <TableCell><code className="font-mono p-1 rounded-sm bg-accent">db-schema-parent</code></TableCell>
                        <TableCell>Read only token</TableCell>
                        <TableCell>Never</TableCell>
                        <TableCell><a href="#" className="text-primary-500 hover:underline">Revoke</a></TableCell>
                    </TableRow>
                    <TableRow>
                        <TableCell>4</TableCell>
                        <TableCell><code className="font-mono p-1 rounded-sm bg-accent">db-testing</code></TableCell>
                        <TableCell>Write only token</TableCell>
                        <TableCell>Never</TableCell>
                        <TableCell><a href="#" className="text-primary-500 hover:underline">Revoke</a></TableCell>
                    </TableRow>
                    <TableRow>
                        <TableCell>5</TableCell>
                        <TableCell><code className="font-mono p-1 rounded-sm bg-accent">db-testing</code></TableCell>
                        <TableCell>Read/Write token</TableCell>
                        <TableCell>Never</TableCell>
                        <TableCell><a href="#" className="text-primary-500 hover:underline">Revoke</a></TableCell>
                    </TableRow>
                </TableBody>
            </Table>
        </div>
    );
}
