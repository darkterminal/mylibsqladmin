import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger
} from "@/components/ui/dropdown-menu"
import { calculateExpirationDate } from "@/lib/utils"
import { GroupDatabaseTokenProps } from "@/types"
import { router } from "@inertiajs/react"
import { KeyIcon, Trash2 } from "lucide-react"
import { toast } from "sonner"
import { AppTooltip } from "../app-tooltip"
import { Button } from "../ui/button"
import ButtonCopyFullAccessToken from "./action-copy-full-access-token"
import ButtonCopyReadOnlyToken from "./action-copy-read-only-token"

export default function ButtonActionGroupToken({ group_token }: { group_token: GroupDatabaseTokenProps }) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <AppTooltip text={`Active until ${calculateExpirationDate(group_token.created_at, group_token.expiration_day)}`}>
                    <Button variant="outline" className="flex items-center bg-green-400 hover:bg-green-500 dark:bg-green-600 dark:hover:bg-green-700">
                        <KeyIcon className="h-3 w-3 mr-1" />
                        <span>Token Active</span>
                    </Button>
                </AppTooltip>
            </DropdownMenuTrigger>
            <DropdownMenuContent>
                <DropdownMenuItem>
                    <ButtonCopyFullAccessToken token={group_token} text="Copy Full Access Token" />
                </DropdownMenuItem>
                <DropdownMenuItem>
                    <ButtonCopyReadOnlyToken token={group_token} text="Copy Read Only Token" />
                </DropdownMenuItem>
                <DropdownMenuItem>
                    <Button
                        variant={'destructive'}
                        size="sm"
                        className="w-full"
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
                                            router.delete(route('group.token.delete', { tokenId: group_token.id }), {
                                                onSuccess: () => {
                                                    toast.dismiss();
                                                    router.visit(route('dashboard.groups'));
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
                        <Trash2 className="h-4 w-4 mr-1 text-white" />
                        <span>Delete</span>
                    </Button>
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

    )
}
