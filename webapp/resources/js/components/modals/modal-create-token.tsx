import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { databaseGroupType } from "@/lib/utils";
import {
    AllowedUser,
    type MostUsedDatabaseMinimalProps,
    type MostUsedDatabaseProps,
    type UserDatabaseTokenProps
} from "@/types";
import { router, useForm, usePage } from "@inertiajs/react";
import { Cylinder, Database, GitBranch } from "lucide-react";
import React, { FormEventHandler, useState } from "react";
import { toast } from "sonner";
import { Button } from "../ui/button";
import { Input } from "../ui/input";
import { Label } from "../ui/label";
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue
} from "../ui/select";

type CreateTokenProps = {
    name: string;
    expiration: number;
    userId: number;
    databaseId: number | undefined;
}

type FlashMessageProps = {
    success?: string;
    error?: string;
    newToken?: UserDatabaseTokenProps;
}

export function ModalCreateToken({
    children,
    users = [],
    mostUsedDatabases,
    onCreateSuccess
}: {
    children: React.ReactNode,
    users?: AllowedUser[],
    mostUsedDatabases: MostUsedDatabaseProps[] | MostUsedDatabaseMinimalProps[],
    onCreateSuccess?: () => void
}) {

    const { props } = usePage();
    const [selectedDatabase, setSelectedDatabase] = useState<string | undefined>(mostUsedDatabases.length > 0 ? String(mostUsedDatabases[0].database_id) : undefined)
    const [selectedUser, setSelectedUser] = useState<string | undefined>(String(props.auth.user.role != "Database Maintainer" ? users.find((user) => user.id === props.auth.user.id)?.id : users[0]?.id))
    const { standaloneDatabases, parentDatabases, childDatabases } = databaseGroupType(mostUsedDatabases)
    const flash = props.flash as FlashMessageProps;
    const [isOpen, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm<CreateTokenProps>({
        name: `token-${Math.floor(Math.random() * Date.now())}`,
        expiration: 0,
        userId: Number(selectedUser),
        databaseId: Number(selectedDatabase)
    });

    const handleSelectChange = (value: string) => {
        setSelectedDatabase(value)
        setData({ ...data, databaseId: Number(value) })
    }

    const handleSelectedUserChange = (value: string) => {
        setSelectedUser(value)
        const user = users.find((user) => user.id === Number(value))
        setData({ ...data, userId: Number(value), name: `${user?.name.replaceAll(' ', '-').toLowerCase()}-token` })
    }

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        if (!data.databaseId || isNaN(data.databaseId)) {
            toast.error('Invalid database selection');
            return;
        }

        post(route('token.create'), {
            preserveScroll: true,
            onSuccess: () => {
                setOpen(false);
                reset('name', 'expiration');
                setSelectedDatabase(undefined);

                if (flash.success) {
                    toast.success(flash.success, {
                        position: 'bottom-center',
                        duration: 5000,
                    })
                }

                if (flash.error) {
                    toast.error(flash.error, {
                        position: 'bottom-center',
                        duration: 5000,
                        style: {
                            backgroundColor: 'var(--destructive)',
                            border: '1px solid var(--destructive)',
                        },
                    })
                }

                router.visit(window.location.href, {
                    only: ['databaseGroups', 'databaseNotInGroup'],
                    replace: true,
                    preserveScroll: true
                });
            },
            onFinish: () => {
                onCreateSuccess?.();
            }
        });
    }

    return (
        <Dialog open={isOpen} onOpenChange={setOpen}>
            <DialogTrigger asChild onClick={() => setOpen(true)}>{children}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Generate New Database Token</DialogTitle>
                    <DialogDescription>
                        Enter the name of the database token you want to create.
                    </DialogDescription>
                </DialogHeader>
                {flash.newToken && (
                    <div className="rounded-md border border-amber-500/50 bg-amber-50/30 p-3 dark:bg-amber-950/30">
                        <p className="mb-2 text-xs font-semibold text-amber-700 dark:text-amber-300">
                            Token created — copy it now. It will not be shown again after this message.
                        </p>
                        <code className="block max-w-full break-all rounded bg-muted px-2 py-1 font-mono text-[10px]">
                            {flash.newToken.full_access_token}
                        </code>
                        <div className="mt-2">
                            <Button
                                type="button"
                                size="sm"
                                variant="outline"
                                onClick={() => navigator.clipboard?.writeText(flash.newToken!.full_access_token)}
                            >
                                Copy Full-Access Token
                            </Button>
                        </div>
                    </div>
                )}
                <form onSubmit={submit} autoComplete="off" className="flex flex-col gap-4">
                    <div className="w-full items-center gap-1.5">
                        <Select value={selectedDatabase} onValueChange={handleSelectChange}>
                            <SelectTrigger className="w-full">
                                <SelectValue placeholder="Select database" />
                            </SelectTrigger>
                            <SelectContent className="max-h-96 overflow-y-auto">
                                {standaloneDatabases.map((db) => (
                                    <SelectItem
                                        key={db.database_id}
                                        value={String(db.database_id)}
                                        className="flex items-center"
                                    >
                                        <Cylinder className="h-3 w-3 mr-2" />
                                        {db.database_name}
                                    </SelectItem>
                                ))}

                                {parentDatabases.map((parentDb) => {
                                    return (
                                        <div key={parentDb.database_id} className="border-t mt-2 pt-2">
                                            <SelectItem
                                                value={String(parentDb.database_id)}
                                                className="font-medium text-primary/80 hover:bg-accent/50"
                                            >
                                                <Database className="h-3 w-3 mr-2" />
                                                {parentDb.database_name} (Schema)
                                            </SelectItem>

                                            <div className="ml-4 border-l-2 border-muted pl-2">
                                                {childDatabases.get(parentDb.database_name.split(' - ')[0])?.map(childDb => (
                                                    <SelectItem
                                                        key={childDb.database_id}
                                                        value={String(childDb.database_id)}
                                                        className="text-muted-foreground"
                                                    >
                                                        <GitBranch className="h-3 w-3 mr-2" />
                                                        {childDb.database_name}
                                                    </SelectItem>
                                                ))}
                                            </div>
                                        </div>
                                    )
                                })}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="flex flex-col w-full space-y-2">
                        <Label htmlFor="granted-to">Select User</Label>
                        <Select value={selectedUser} onValueChange={handleSelectedUserChange}>
                            <SelectTrigger>
                                <SelectValue placeholder="Select user" />
                            </SelectTrigger>
                            <SelectContent>
                                {users && users.map((user) => (
                                    <SelectItem key={user.id} value={String(user.id)}>
                                        {user.name === props.auth.user.name ? `Me - ${user.roles[0].name}` : <span>{user.name} - {user.roles[0].name}</span>}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="flex flex-col w-full space-y-2">
                        <Label htmlFor="expiration">Expiration (days)</Label>
                        <Input
                            type="number"
                            id="expiration"
                            name="expiration"
                            value={data.expiration}
                            onChange={(e) => setData('expiration', Number(e.target.value))}
                            placeholder="Expiration in a day"
                            className="w-full"
                            min={0}
                            tabIndex={2}
                            required
                        />
                        <span className="text-muted-foreground text-xs">
                            Note: Use 0 for no expiration
                        </span>
                    </div>
                    <Button variant={'default'} type="submit" disabled={processing || !data.name}>Generate Token</Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
