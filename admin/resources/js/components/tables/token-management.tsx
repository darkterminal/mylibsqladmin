import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow
} from "@/components/ui/table";
import { useCustomEvent } from "@/hooks/use-custom-event";
import { type UserDatabaseTokenProps } from "@/types";
import { router } from "@inertiajs/react";
import ButtonCopyFullAccessToken from "../button-actions/action-copy-full-access-token";
import ButtonCopyReadOnlyToken from "../button-actions/action-copy-read-only-token";
import ButtonCopyShellCommand from "../button-actions/action-copy-shell-command";
import ButtonDeleteToken from "../button-actions/action-delete-token";
import ButtonOpenDatabaseStudio from "../button-actions/action-open-database-studio";

export default function TableTokenManagement({
    userDatabaseTokens
}: {
    userDatabaseTokens: UserDatabaseTokenProps[]
}) {

    useCustomEvent('token-is-deleted', () => {
        router.reload({ only: ['userDatabaseTokens'] });
    })

    useCustomEvent('token-is-created', () => {
        router.reload({ only: ['userDatabaseTokens'] });
    })

    return (
        <Table>
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
                {userDatabaseTokens.length > 0 ? (
                    userDatabaseTokens.map((token, index) => (
                        <TableRow key={index}>
                            <TableCell className="font-mono text-xs">{index + 1}</TableCell>
                            <TableCell>{token.database.database_name}</TableCell>
                            <TableCell>{token.name}</TableCell>
                            <TableCell>{token.expiration_day}</TableCell>
                            <TableCell className="flex gap-2">
                                <ButtonDeleteToken token={token} />
                                <ButtonCopyReadOnlyToken token={token} />
                                <ButtonCopyFullAccessToken token={token} />
                                <ButtonOpenDatabaseStudio databaseName={token.database.database_name} />
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
