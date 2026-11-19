import { AppTooltip } from "@/components/app-tooltip";
import ButtonDelete from "@/components/button-actions/action-delete";
import ButtonRestore from "@/components/button-actions/action-restore";
import { Button } from "@/components/ui/button";
import { Dialog, DialogContent, DialogDescription, DialogFooter, DialogHeader, DialogTitle } from "@/components/ui/dialog";
import { Input } from "@/components/ui/input";
import { Label } from "@/components/ui/label";
import { SharedData } from "@/types";
import { Link, router, usePage } from "@inertiajs/react";
import { EyeIcon } from "lucide-react";
import { FormEventHandler, useRef, useState } from "react";
import { toast } from "sonner";
import { UserDataTable } from "./user-columns";

export default function DataTableActions({ user }: { user: UserDataTable }) {
    const { auth } = usePage<SharedData>().props;
    const isArchive = route().current() === 'user.archive';

    const passwordInput = useRef<HTMLInputElement>(null);
    const [password, setPassword] = useState('');
    const [processing, setProcessing] = useState(false);
    const [passwordError, setPasswordError] = useState<string | null>(null);
    const [isForceDialogOpen, setIsForceDialogOpen] = useState(false);

    const handleDelete = () => {
        toast('Are you sure you want to archive this user?', {
            description: 'The account is soft-deleted: it can no longer sign in or use tokens, and its record is kept. Restorable from the archive.',
            position: 'top-center',
            duration: 5000,
            action: {
                label: 'Archive',
                onClick: () => router.delete(route('user.delete', user.id), {
                    onSuccess: () => {
                        toast.success('User archived successfully', {
                            duration: 1500,
                            onAutoClose() {
                                router.visit(window.location.href, {
                                    preserveScroll: true
                                });
                            },
                        });
                    }
                })
            },
        })
    }

    const handleRestore = () => {
        toast('Restore this user?', {
            description: 'The account is restored and can sign in again. You can archive it again at any time.',
            position: 'top-center',
            duration: 5000,
            action: {
                label: 'Restore',
                onClick: () => router.put(route('user.restore', user.id), undefined, {
                    onSuccess: () => {
                        toast.success('User restored successfully', {
                            duration: 1500,
                            onAutoClose() {
                                router.visit(window.location.href, {
                                    preserveScroll: true
                                });
                            },
                        });
                    }
                })
            },
        })
    }

    const submitForceDelete: FormEventHandler = (e) => {
        e.preventDefault();
        setProcessing(true);
        setPasswordError(null);

        router.delete(route('user.force-delete', user.id), {
            data: { password },
            preserveScroll: true,
            onSuccess: () => {
                setIsForceDialogOpen(false);
                setPassword('');
                toast.success('User permanently deleted', { duration: 3000 });
            },
            onError: (errors: Record<string, string>) => {
                setPasswordError(errors.password ?? 'Incorrect password. Please try again.');
                passwordInput.current?.focus();
            },
            onFinish: () => {
                setProcessing(false);
            },
        });
    }

    if (!isArchive) {
        return (
            <div className="flex items-center gap-2">
                {user.id !== auth.user.id && (
                    <ButtonDelete
                        handleDelete={handleDelete}
                        text="Archive User"
                    />
                )}
                <AppTooltip text="View User">
                    <Button variant={"default"} asChild>
                        <Link href={route('user.show', user.id)}>
                            <EyeIcon className="h-4 w-4" />
                        </Link>
                    </Button>
                </AppTooltip>
            </div>
        )
    }

    return (
        <div className="flex items-center gap-2">
            <Dialog open={isForceDialogOpen} onOpenChange={(open) => { setIsForceDialogOpen(open); if (!open) { setPassword(''); setPasswordError(null); } }}>
                <ButtonDelete text="Permanently Delete User" handleDelete={() => setIsForceDialogOpen(true)} />
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Permanently delete this user?</DialogTitle>
                        <DialogDescription>
                            This cannot be undone. All records for <span className="font-medium text-foreground">{user.name}</span> (@{user.username}) will be removed forever.
                            Enter your own password to confirm.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={submitForceDelete} className="space-y-4">
                        <div className="grid gap-2">
                            <Label htmlFor="force-delete-password">Your password</Label>
                            <Input
                                id="force-delete-password"
                                ref={passwordInput}
                                type="password"
                                autoComplete="current-password"
                                value={password}
                                onChange={(e) => setPassword(e.target.value)}
                                required
                            />
                            {passwordError && <p className="text-xs text-destructive">{passwordError}</p>}
                        </div>
                        <DialogFooter className="gap-2">
                            <Button type="button" variant="outline" onClick={() => setIsForceDialogOpen(false)}>Cancel</Button>
                            <Button type="submit" variant="destructive" disabled={processing || password === ''}>
                                {processing ? 'Deleting…' : 'Permanently delete'}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
            <ButtonRestore handleRestore={handleRestore} text="Restore User" />
        </div>
    )
}
