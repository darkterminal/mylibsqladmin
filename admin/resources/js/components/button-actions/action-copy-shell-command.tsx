import { AppTooltip } from "@/components/app-tooltip";
import { Button } from "@/components/ui/button";
import useCopyToClipboard from "@/hooks/copy-to-clipboard";
import { type UserDatabaseTokenProps } from "@/types";
import { CheckIcon, TerminalIcon } from "lucide-react";

export default function ButtonCopyShellCommand({ token }: { token: UserDatabaseTokenProps }) {
    const { copiedText, copyToClipboard } = useCopyToClipboard();
    return (
        <AppTooltip text="Copy Shell Command">
            <Button
                variant="default"
                size="sm"
                onClick={() => {
                    const protocol = window.location.protocol
                    const hostname = window.location.hostname
                    const cmd = `turso db shell $(echo "${protocol}//${token.database.database_name}.${hostname}:8080?authToken=${token.full_access_token}")`
                    copyToClipboard(cmd, token.name, 'shell')
                }}
            >
                {copiedText[`${token.name}-shell`] ? (
                    <CheckIcon className="h-4 w-4" />
                ) : (
                    <TerminalIcon className="h-4 w-4" />
                )}
            </Button>
        </AppTooltip>
    )
}
