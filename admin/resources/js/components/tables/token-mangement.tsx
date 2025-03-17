import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger
} from "@/components/ui/dropdown-menu";
import {
    Table,
    TableBody,
    TableCaption,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table";
import { type UserDatabaseTokenProps } from "@/types";
import { CheckIcon, CopyIcon, Ellipsis, Trash } from "lucide-react";
import { useState } from "react";
import { Button } from "../ui/button";

export default function TableTokenManagement({
    userDatabaseTokens
}: {
    userDatabaseTokens: UserDatabaseTokenProps[]
}) {
    const [copiedTokens, setCopiedTokens] = useState<Record<string, boolean>>({});

    const copyToClipboard = async (value: string, tokenId: string, type: 'full' | 'read') => {
        try {
            await navigator.clipboard.writeText(value);
            const key = `${tokenId}-${type}`;
            setCopiedTokens(prev => ({ ...prev, [key]: true }));
            setTimeout(() => {
                setCopiedTokens(prev => ({ ...prev, [key]: false }));
            }, 2000);
        } catch (err) {
            console.error('Failed to copy text: ', err);
        }
    };

    return (
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
                {userDatabaseTokens.length > 0 ? (
                    userDatabaseTokens.map((token, index) => (
                        <TableRow key={index}>
                            <TableCell className="font-mono text-xs">{index + 1}</TableCell>
                            <TableCell>{token.database.database_name}</TableCell>
                            <TableCell>{token.name}</TableCell>
                            <TableCell>{token.expiration_day}</TableCell>
                            <TableCell>
                                <DropdownMenu>
                                    <DropdownMenuTrigger asChild>
                                        <Button variant="outline" size="sm"><Ellipsis className="h-3 w-3" /></Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="end">
                                        <DropdownMenuItem
                                            className="hover:cursor-pointer"
                                            onClick={(e) => {
                                                e.preventDefault();
                                                copyToClipboard(token.full_access_token, token.name, 'full')
                                            }}
                                            aria-label={copiedTokens[`${token.name}-full`] ? "Copied!" : "Copy to clipboard"}
                                        >
                                            {copiedTokens[`${token.name}-full`] ? (
                                                <CheckIcon className="mr-2 h-4 w-4 text-primary dark:text-primary-foreground" />
                                            ) : (
                                                <CopyIcon className="mr-2 h-4 w-4" />
                                            )} Copy Full Access Token
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            className="hover:cursor-pointer"
                                            onClick={(e) => {
                                                e.preventDefault();
                                                copyToClipboard(token.read_only_token, token.name, 'read')
                                            }}
                                            aria-label={copiedTokens[`${token.name}-read`] ? "Copied!" : "Copy to clipboard"}
                                        >
                                            {copiedTokens[`${token.name}-read`] ? (
                                                <CheckIcon className="mr-2 h-4 w-4 text-primary dark:text-primary-foreground" />
                                            ) : (
                                                <CopyIcon className="mr-2 h-4 w-4" />
                                            )} Copy Read Only Token
                                        </DropdownMenuItem>
                                        <DropdownMenuItem
                                            className="hover:cursor-pointer bg-red-500 hover:!bg-red-600 text-white hover:!text-white"
                                            onClick={(e) => {
                                                e.preventDefault();
                                                console.log(token);
                                            }}
                                        >
                                            <Trash className="mr-2 h-4 w-4 text-white" /> Delete
                                        </DropdownMenuItem>
                                    </DropdownMenuContent>
                                </DropdownMenu>
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
