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
    databaseId: number | undefined;
}

type FlashMessageProps = {
    success?: string;
    error?: string;
    newToken?: UserDatabaseTokenProps;
}

export function ModalCreateToken({
    children,
    mostUsedDatabases,
    onCreateSuccess
}: {
    children: React.ReactNode,
    mostUsedDatabases: MostUsedDatabaseProps[] | MostUsedDatabaseMinimalProps[],
    onCreateSuccess?: () => void
}) {

    const [selectedDatabase, setSelectedDatabase] = useState<string | undefined>(mostUsedDatabases.length > 0 ? String(mostUsedDatabases[0].database_id) : undefined)
    const { standaloneDatabases, parentDatabases, childDatabases } = databaseGroupType(mostUsedDatabases)
    const { props } = usePage();
    const flash = props.flash as FlashMessageProps;
    const [isOpen, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm<CreateTokenProps>({
        name: '',
        expiration: 30,
        databaseId: Number(selectedDatabase)
    });

    const handleSelectChange = (value: string) => {
        setSelectedDatabase(value)
        setData({ ...data, databaseId: Number(value) })
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
                        <Label htmlFor="name">Token Name</Label>
                        <Input
                            type="text"
                            id="name"
                            name="name"
                            value={data.name}
                            onChange={(e) => setData('name', String(e.target.value))}
                            placeholder="Token Name"
                            className="w-full"
                            autoFocus
                            tabIndex={1}
                            required
                        />
                    </div>
                    <div className="flex flex-col w-full space-y-2">
                        <Label htmlFor="expiration">Expiration</Label>
                        <Input
                            type="number"
                            id="expiration"
                            name="expiration"
                            value={data.expiration}
                            onChange={(e) => setData('expiration', Number(e.target.value))}
                            placeholder="Expiration in a day"
                            className="w-full"
                            tabIndex={2}
                            required
                        />
                    </div>
                    <Button variant={'default'} type="submit" disabled={processing || !data.name}>Generate Token</Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
