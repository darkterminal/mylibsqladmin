import { AppTooltip } from "@/components/app-tooltip";
import { ModalCreateToken } from "@/components/modals/modal-create-token";
import TableTokenManagement from "@/components/tables/token-management";
import { Button } from "@/components/ui/button";
import { Card, CardContent, CardHeader } from "@/components/ui/card";
import AppLayout from "@/layouts/app-layout";
import {
    type BreadcrumbItem,
    type MostUsedDatabaseProps,
    type UserDatabaseTokenProps
} from "@/types";
import { Head } from "@inertiajs/react";
import { Plus } from "lucide-react";

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Tokens',
        href: '/dashboard/tokens',
    }
];

export default function DatabaseToken({
    mostUsedDatabases,
    isAllTokenized,
    userDatabaseTokens
}: {
    mostUsedDatabases: MostUsedDatabaseProps[],
    isAllTokenized: boolean,
    userDatabaseTokens: UserDatabaseTokenProps[]
}) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Tokens" />
            <div className="flex h-full flex-1 p-4">
                <Card className="flex-1">
                    <CardHeader className="flex flex-row gap-1.5 justify-between items-center">
                        <h2 className="text-2xl font-semibold tracking-tight">Database Token Management</h2>
                        <ModalCreateToken mostUsedDatabases={mostUsedDatabases}>
                            <Button variant={'default'} disabled={isAllTokenized}>
                                <AppTooltip text={isAllTokenized ? 'All databases are tokenized' : 'Generate New Token'}>
                                    <>
                                        <Plus className="h-4 w-4" />
                                        <span>{isAllTokenized ? 'All databases are tokenized' : 'Generate New Token'}</span>
                                    </>
                                </AppTooltip>
                            </Button>
                        </ModalCreateToken>
                    </CardHeader>
                    <CardContent>
                        <TableTokenManagement userDatabaseTokens={userDatabaseTokens} />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
