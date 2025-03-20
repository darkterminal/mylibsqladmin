import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger
} from "@/components/ui/dropdown-menu"
import { GroupDatabaseTokenProps } from "@/types"
import { KeyIcon } from "lucide-react"
import { Button } from "../ui/button"
import ButtonCopyFullAccessToken from "./action-copy-full-access-token"
import ButtonCopyReadOnlyToken from "./action-copy-read-only-token"

export default function ButtonActionGroupToken({ group_token }: { group_token: GroupDatabaseTokenProps }) {
    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" className="flex items-center bg-green-400 hover:bg-green-500 dark:bg-green-600 dark:hover:bg-green-700">
                    <KeyIcon className="h-3 w-3 mr-1" />
                    <span>Token Active</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent>
                <DropdownMenuItem>
                    <ButtonCopyFullAccessToken token={group_token} text="Copy Full Access Token" />
                </DropdownMenuItem>
                <DropdownMenuItem>
                    <ButtonCopyReadOnlyToken token={group_token} text="Copy Read Only Token" />
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>

    )
}
