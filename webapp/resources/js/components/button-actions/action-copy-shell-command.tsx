import { AppTooltip } from "@/components/app-tooltip";
import { Button } from "@/components/ui/button";
import useCopyToClipboard from "@/hooks/copy-to-clipboard";
import { Configs, SharedData, type UserDatabaseTokenProps } from "@/types";
import { usePage } from '@inertiajs/react';
import { isTokenHash } from "@/lib/utils";
import { CheckIcon, TerminalIcon, EyeOff } from "lucide-react";

export default function ButtonCopyShellCommand({ token }: { token: UserDatabaseTokenProps }) {
    const { props } = usePage<SharedData>();
    const { copiedText, copyToClipboard } = useCopyToClipboard();
    const { sqldHost, sqldPort } = props.configs as Configs;

    if (isTokenHash(token.full_access_token)) {
        return (
            <AppTooltip text="Token shown once at creation time; only a stored hash is kept">
                <Button
                    variant="default"
                    size="sm"
                    disabled
                >
                    <EyeOff className="h-4 w-4" />
                </Button>
            </AppTooltip>
        );
    }

    return (
        <AppTooltip text="Copy Shell Command">
            <Button
                variant="default"
                size="sm"
                onClick={() => {
                    const protocol = window.location.protocol
                    const hostname = window.location.hostname
                    const cmd = `turso db shell $(echo "${protocol}//${token.database.database_name}.${sqldHost}:${sqldPort}/?authToken=${token.full_access_token}")`
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
