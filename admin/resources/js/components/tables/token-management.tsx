import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow
} from "@/components/ui/table";
import { PaginatedResults, type UserDatabaseTokenProps } from "@/types";
import ButtonCopyFullAccessToken from "../button-actions/action-copy-full-access-token";
import ButtonCopyReadOnlyToken from "../button-actions/action-copy-read-only-token";
import ButtonCopyShellCommand from "../button-actions/action-copy-shell-command";
import ButtonDeleteToken from "../button-actions/action-delete-token";
import ButtonOpenDatabaseStudio from "../button-actions/action-open-database-studio";

export default function TableTokenManagement({
    userDatabaseTokens
}: {
    userDatabaseTokens: PaginatedResults<UserDatabaseTokenProps>
}) {
    console.log(userDatabaseTokens);
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead className="w-[100px]">#</TableHead>
                    <TableHead>Team Name</TableHead>
                    <TableHead>Database Name</TableHead>
                    <TableHead>Token Name</TableHead>
                    <TableHead>Expiration</TableHead>
                    <TableHead>Created By</TableHead>
                    <TableHead>Action</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {userDatabaseTokens.data.length > 0 ? (
                    userDatabaseTokens.data.map((token, index) => (
                        <TableRow key={token.id}>
                            <TableCell className="font-mono text-xs">
                                {(userDatabaseTokens.current_page - 1) * userDatabaseTokens.per_page + index + 1}
                            </TableCell>
                            <TableCell>{token.team?.name}</TableCell>
                            <TableCell>{token.database?.database_name}</TableCell>
                            <TableCell>{token.name}</TableCell>
                            <TableCell>{token.expiration_day}</TableCell>
                            <TableCell>{token.user.name}</TableCell>
                            <TableCell className="flex gap-2">
                                <ButtonDeleteToken token={token} />
                                <ButtonCopyReadOnlyToken token={token} />
                                <ButtonCopyFullAccessToken token={token} />
                                <ButtonOpenDatabaseStudio databaseName={token.database?.database_name} />
                                <ButtonCopyShellCommand token={token} />
                            </TableCell>
                        </TableRow>
                    ))
                ) : (
                    <TableRow>
                        <TableCell colSpan={5} className="text-center">
                            No data available
                        </TableCell>
                    </TableRow>
                )}
            </TableBody>
        </Table>
    );
}
