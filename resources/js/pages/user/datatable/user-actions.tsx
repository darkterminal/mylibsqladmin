import { AppTooltip } from "@/components/app-tooltip";
import ButtonDelete from "@/components/button-actions/action-delete";
import ButtonRestore from "@/components/button-actions/action-restore";
import { Button } from "@/components/ui/button";
import { SharedData } from "@/types";
import { Link, router, usePage } from "@inertiajs/react";
import { EyeIcon } from "lucide-react";
import { toast } from "sonner";
import { UserDataTable } from "./user-columns";

export default function DataTableActions({ user }: { user: UserDataTable }) {
    const { auth } = usePage<SharedData>().props;

    const handleDelete = () => {
        toast('Are you sure you want to delete this user?', {
            description: 'This action cannot be undone.',
            position: 'top-center',
            duration: 5000,
            action: {
                label: 'Delete',
                onClick: () => router.delete(route('user.delete', user.id), {
                    onSuccess: () => {
                        toast.success('User deleted successfully', {
                            duration: 1500,
                            onAutoClose(toast) {
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
        toast('Are you sure you want to restore this user?', {
            description: 'This action cannot be undone.',
            position: 'top-center',
            duration: 5000,
            action: {
                label: 'Restore',
                onClick: () => router.put(route('user.restore', user.id), undefined, {
                    onSuccess: () => {
                        toast.success('User restored successfully', {
                            duration: 1500,
                            onAutoClose(toast) {
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

    const handleForceDelete = () => {
        toast('Are you sure you want to force delete this user?', {
            description: 'This action cannot be undone.',
            position: 'top-center',
            duration: 5000,
            action: {
                label: 'Delete',
                onClick: () => router.delete(route('user.force-delete', user.id))
            },
        })
    }

    return (
        <div className="flex items-center gap-2">
            {route().current() !== 'user.archive' ? (
                <>
                    {user.id !== auth.user.id && (
                        <ButtonDelete
                            handleDelete={handleDelete}
                            text="Delete User"
                        />
                    )}
                    <AppTooltip text="View User">
                        <Button variant={"default"} asChild>
                            <Link href={route('user.show', user.id)}>
                                <EyeIcon className="h-4 w-4" />
                            </Link>
                        </Button>
                    </AppTooltip>
                </>
            ) : (
                <>
                    <ButtonDelete
                        handleDelete={handleForceDelete}
                        text="Delete User"
                    />
                    <ButtonRestore handleRestore={handleRestore} text="Restore User" />
                </>
            )}
        </div>
    )
}
