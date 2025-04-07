import { AppTooltip } from "@/components/app-tooltip";
import { Button } from "@/components/ui/button";
import useCopyToClipboard from "@/hooks/copy-to-clipboard";
import { type GroupDatabaseTokenProps, type UserDatabaseTokenProps } from "@/types";
import { CheckIcon, EyeIcon } from "lucide-react";

export default function ButtonCopyReadOnlyToken({
    token,
    text = undefined,
}: {
    token: UserDatabaseTokenProps | GroupDatabaseTokenProps | string;
    text?: string;
}) {
    const { copiedText, copyToClipboard } = useCopyToClipboard();

    return (
        <AppTooltip text="Copy Read Only Token">
            <Button
                variant="outline"
                size="sm"
                onClick={(e) => {
                    e.preventDefault();
                    copyToClipboard(
                        typeof token === "string" ? token : token.read_only_token,
                        typeof token === "string" ? "token" : token.name,
                        "read"
                    );
                }}
            >
                {copiedText[`${typeof token === "string" ? "token" : token.name}-read`] ? (
                    <CheckIcon className="h-4 w-4 text-primary dark:text-primary-foreground" />
                ) : (
                    <EyeIcon className="h-4 w-4" />
                )}
                {text && <span className="ml-2">{text}</span>}
            </Button>
        </AppTooltip>
    );
}
