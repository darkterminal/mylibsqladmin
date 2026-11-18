import { Button } from "@/components/ui/button";
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { useForm } from "@inertiajs/react";
import React, { FormEventHandler, useState } from "react";
import { toast } from "sonner";

type CreateGroupTokenProps = {
    name: string;
    expiration: number;
    group_id: number;
}

export function ModalCreateGroupToken({
    groupId,
    children,
    onSuccess
}: {
    groupId: number
    children: React.ReactNode
    onSuccess?: () => void
}) {
    const [isOpen, setOpen] = useState(false);
    const { data, setData, post, processing, errors, reset } = useForm<CreateGroupTokenProps>({
        name: `#GT${groupId}-` + Math.floor(Math.random() * Date.now()),
        expiration: 30,
        group_id: groupId
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();
        post(route('group.token.create', { group: groupId }), {
            onSuccess: () => {
                // Do NOT navigate — the reveal panel renders from the flash
                // and needs the props to survive until the user saves it.
                setOpen(false);
                reset();
                onSuccess?.();
            },
            onError: () => {
                toast.error('Failed to create group token');
            }
        });
    }

    return (
        <Dialog open={isOpen} onOpenChange={setOpen}>
            <DialogTrigger asChild>{children}</DialogTrigger>
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Create Group Token</DialogTitle>
                    <DialogDescription>
                        Generate a token that applies to all databases in this group
                    </DialogDescription>
                </DialogHeader>
                <form onSubmit={submit} className="space-y-4" autoComplete="off">
                    <div className="space-y-2">
                        <Label htmlFor="expiration">Expiration (Days)</Label>
                        <Input
                            type="number"
                            id="expiration"
                            value={data.expiration}
                            onChange={(e) => setData('expiration', Number(e.target.value))}
                            min="1"
                            required
                        />
                    </div>

                    <Button type="submit" disabled={processing}>
                        {processing ? "Creating..." : "Create Group Token"}
                    </Button>
                </form>
            </DialogContent>
        </Dialog>
    );
}
