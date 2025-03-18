import { AppTooltip } from "@/components/app-tooltip";
import { Button } from "@/components/ui/button";
import useCopyToClipboard from "@/hooks/copy-to-clipboard";
import { type UserDatabaseTokenProps } from "@/types";
import { CheckIcon, EyeIcon } from "lucide-react";

export default function ButtonCopyReadOnlyToken({ token }: { token: UserDatabaseTokenProps }) {
    const { copiedText, copyToClipboard } = useCopyToClipboard();
    return (
        <AppTooltip text="Copy Read Only Token">
            <Button
                variant="outline"
                size="sm"
                onClick={(e) => {
                    e.preventDefault();
                    copyToClipboard(token.read_only_token, token.name, 'read')
                }}
            >
                {copiedText[`${token.name}-full`] ? (
                    <CheckIcon className="h-4 w-4 text-primary dark:text-primary-foreground" />
                ) : (
                    <EyeIcon className="h-4 w-4" />
                )}
            </Button>
        </AppTooltip>
    )
}
