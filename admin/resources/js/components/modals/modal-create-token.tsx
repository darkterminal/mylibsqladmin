import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { databaseGroupType } from "@/lib/utils";
import { type MostUsedDatabaseProps } from "@/types";
import { useForm, usePage } from "@inertiajs/react";
import { Cylinder, Database, GitBranch } from "lucide-react";
import React, { FormEventHandler, useState } from "react";
import { toast } from "sonner";
import { Button } from "../ui/button";
import { Input } from "../ui/input";
import { Label } from "../ui/label";
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "../ui/select";

type CreateTokenProps = {
    name: string;
    expiration: number;
    databaseId: number | undefined;
}

type FlashMessageProps = {
    success?: string;
    error?: string;
}

export function ModalCreateToken({ children, mostUsedDatabases }: { children: React.ReactNode, mostUsedDatabases: MostUsedDatabaseProps[] }) {

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
        post(route('token.create'), {
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
            },
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
                            <SelectContent>
                                {standaloneDatabases.sort((a, b) => a.database_id - b.database_id).map((db) => (
                                    <SelectItem key={db.database_id} value={String(db.database_id)}>
                                        <Cylinder className="h-3 w-3" /> {db.database_name}
                                    </SelectItem>
                                ))}
                                {parentDatabases.map((db) => (
                                    <React.Fragment key={db.database_id}>
                                        <SelectItem key={db.database_id} value={String(db.database_id)}>
                                            <Database className="h-3 w-3" /> {db.database_name}
                                        </SelectItem>
                                        {childDatabases.get(db.database_name)?.map((db) => (
                                            <SelectItem key={db.database_id} value={String(db.database_id)}>
                                                <GitBranch className="h-3 w-3" /> {db.database_name}
                                            </SelectItem>
                                        ))}
                                    </React.Fragment>
                                ))}
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
