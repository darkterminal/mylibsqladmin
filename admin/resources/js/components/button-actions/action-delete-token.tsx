import { AppTooltip } from "@/components/app-tooltip";
import { Button } from "@/components/ui/button";
import { triggerEvent } from "@/hooks/use-custom-event";
import { type UserDatabaseTokenProps } from "@/types";
import { router } from "@inertiajs/react";
import { Trash2 } from "lucide-react";
import { toast } from "sonner";

export default function ButtonDeleteToken({ token }: { token: UserDatabaseTokenProps }) {
    return (
        <AppTooltip text="Delete Token">
            <Button
                variant="destructive"
                size="sm"
                onClick={() => {
                    toast("Are you sure you want to delete this token?", {
                        description: "This action cannot be undone.",
                        duration: 7000,
                        position: "top-center",
                        style: {
                            cursor: "pointer",
                        },
                        action: (
                            <Button
                                variant="destructive"
                                size="sm"
                                onClick={() => {
                                    router.delete(`/tokens/delete/${token.id}`, {
                                        onSuccess: () => {
                                            toast.dismiss();
                                            triggerEvent('token-is-deleted', { id: token.id })
                                        }
                                    });
                                }}
                            >
                                Delete
                            </Button>
                        ),
                    });
                }}
            >
                <Trash2 className="h-4 w-4" />
            </Button>
        </AppTooltip>
    )
}
