import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { router, useForm } from "@inertiajs/react";
import { FormEventHandler, useState } from "react";
import { Button } from "../ui/button";
import { Checkbox } from "../ui/checkbox";
import { Input } from "../ui/input";
import { Label } from "../ui/label";

type CreateDatabaseProps = {
    database: string;
    isSchema: boolean;
}

export function ModalCreateDatabase({ children }: { children: React.ReactNode }) {

    const [isOpen, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm<CreateDatabaseProps>({
        database: '',
        isSchema: false
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('database.create'), {
            onSuccess: () => {
                setOpen(false);
                reset('database', 'isSchema');
            },
            onFinish: () => router.get(`?database=${data.database}`),
        });
    }

    return (
        <Dialog open={isOpen} onOpenChange={setOpen}>
            <DialogTrigger onClick={() => setOpen(true)}>{children}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create New Database</DialogTitle>
                    <DialogDescription>
                        Enter the name of the database you want to create.
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} autoComplete="off" className="flex flex-col gap-4">
                    <div className="w-full items-center gap-1.5">
                        <Input
                            type="text"
                            id="database"
                            name="database"
                            value={data.database}
                            onChange={(e) => setData('database', e.target.value.replace(/[^a-zA-Z0-9-_]/g, ''))}
                            placeholder="Database Name"
                            className="w-full"
                            autoFocus
                            tabIndex={1}
                            required
                        />
                        <span className="text-xs italic">Only alphanumeric characters, dashes, and underscores are allowed</span>
                    </div>
                    <div className="flex items-center space-x-3">
                        <Checkbox id="isSchema" name="isSchema" checked={data.isSchema} onClick={() => setData('isSchema', !data.isSchema)} tabIndex={2} />
                        <Label htmlFor="isSchema">Schema Database</Label>
                    </div>
                    <Button variant={'default'} type="submit">Create Database</Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
