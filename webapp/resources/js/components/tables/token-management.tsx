import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow
} from "@/components/ui/table";
import { usePermission } from "@/lib/auth";
import { PaginatedResults, type UserDatabaseTokenProps } from "@/types";
import { Cylinder, Database, GitBranch } from "lucide-react";
import ButtonDeleteToken from "../button-actions/action-delete-token";
import ButtonOpenDatabaseStudio from "../button-actions/action-open-database-studio";

const schemaIcon = (isSchema: unknown) => {
    // is_schema: boolean (newer) or parent name string (schema children)
    if (typeof isSchema === "boolean") {
        return isSchema ? Database : Cylinder;
    }
    if (isSchema === "" || isSchema === "0" || isSchema === 0) return Cylinder;
    if (isSchema === "1" || isSchema === 1) return Database;
    return GitBranch;
};

function ExpirationCell({ token }: { token: UserDatabaseTokenProps }) {
    if (token.expiration_state === "never") {
        return (
            <div className="flex flex-col">
                <span className="text-sm">Never</span>
                <span className="text-xs text-muted-foreground">no expiration</span>
            </div>
        );
    }

    const remaining = token.days_remaining;

    return (
        <div className="flex flex-col">
            <span className="text-sm tabular-nums">
                {token.expires_at}
                {token.expiration_state === "expired" && (
                    <span className="ml-2 inline-flex items-center rounded-md border border-destructive/50 bg-destructive/10 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-destructive">
                        Expired
                    </span>
                )}
            </span>
            {remaining !== null && (
                <span className="text-xs text-muted-foreground">
                    {token.expiration_state === "expired"
                        ? `${Math.abs(remaining)} day${Math.abs(remaining) === 1 ? "" : "s"} overdue`
                        : `in ${remaining} day${remaining === 1 ? "" : "s"}`}
                </span>
            )}
        </div>
    );
}

export default function TableTokenManagement({
    userDatabaseTokens
}: {
    userDatabaseTokens: PaginatedResults<UserDatabaseTokenProps>
}) {
    const { can } = usePermission();

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead className="w-[60px]">#</TableHead>
                    <TableHead>Database</TableHead>
                    <TableHead>Access For</TableHead>
                    <TableHead>Token Name</TableHead>
                    <TableHead>Expiration</TableHead>
                    <TableHead>Created</TableHead>
                    <TableHead>Action</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {userDatabaseTokens.data.length > 0 ? (
                    userDatabaseTokens.data.map((token, index) => {
                        const SchemaIcon = schemaIcon(token.database?.is_schema);

                        return (
                            <TableRow key={token.id}>
                                <TableCell className="font-mono text-xs">
                                    {(userDatabaseTokens.current_page - 1) * userDatabaseTokens.per_page + index + 1}
                                </TableCell>
                                <TableCell>
                                    <div className="flex items-center gap-2">
                                        <SchemaIcon className="h-4 w-4 shrink-0 text-muted-foreground" aria-hidden="true" />
                                        <div className="min-w-0">
                                            <div className="truncate text-sm">{token.database?.database_name ?? "—"}</div>
                                            {token.team?.name ? (
                                                <div className="truncate text-xs text-muted-foreground">{token.team.name}</div>
                                            ) : null}
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div className="min-w-0">
                                        <div className="truncate text-sm">{token.user?.name ?? "Unknown user"}</div>
                                        <div className="truncate text-xs text-muted-foreground">by {token.created_by}</div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <div className="min-w-0">
                                        <div className="truncate text-sm" title={token.name}>{token.name}</div>
                                        <div className="truncate font-mono text-xs text-muted-foreground" title="Fingerprint (last 8 of the stored hash)">
                                            fp&nbsp;{token.fingerprint}
                                        </div>
                                    </div>
                                </TableCell>
                                <TableCell>
                                    <ExpirationCell token={token} />
                                </TableCell>
                                <TableCell className="text-muted-foreground tabular-nums">
                                    {token.created_at ? token.created_at.slice(0, 10) : "—"}
                                </TableCell>
                                <TableCell>
                                    <div className="flex items-center gap-2">
                                {/* All tokens are SHA-256-hashed (pre-v1.5.0
                                    plaintext rows were rehashed by migration) —
                                    there is nothing copyable to offer here. */}
                                <ButtonOpenDatabaseStudio databaseName={token.database?.database_name} />
                                        {can('delete-database-tokens') && <ButtonDeleteToken token={token} />}
                                    </div>
                                </TableCell>
                            </TableRow>
                        );
                    })
                ) : (
                    <TableRow>
                        <TableCell colSpan={7} className="py-8 text-center text-sm text-muted-foreground">
                            No tokens match — try a different search, or generate one above.
                        </TableCell>
                    </TableRow>
                )}
            </TableBody>
        </Table>
    );
}
