import { AppTooltip } from "@/components/app-tooltip";
import { Button } from "@/components/ui/button";
import useCopyToClipboard from "@/hooks/copy-to-clipboard";
import { type GroupDatabaseTokenProps, type UserDatabaseTokenProps } from "@/types";
import { isTokenHash } from "@/lib/utils";
import { CheckIcon, EyeIcon, EyeOff } from "lucide-react";

export default function ButtonCopyReadOnlyToken({
    token,
    text = undefined,
}: {
    token: UserDatabaseTokenProps | GroupDatabaseTokenProps | string;
    text?: string;
}) {
    const { copiedText, copyToClipboard } = useCopyToClipboard();

    const tokenValue = typeof token === "string" ? token : token.read_only_token;
    const tokenName = typeof token === "string" ? "token" : token.name;

    if (isTokenHash(tokenValue)) {
        return (
            <AppTooltip text="Token shown once at creation time; only a stored hash is kept">
                <Button
                    variant="outline"
                    size="sm"
                    disabled
                    aria-label="Token shown once at creation time; only a stored hash is kept"
                >
                    <EyeOff className="h-4 w-4" />
                    {text && <span className="ml-2">{text}</span>}
                </Button>
            </AppTooltip>
        );
    }

    return (
        <AppTooltip text="Copy Read Only Token">
            <Button
                variant="outline"
                size="sm"
                aria-label={text ?? "Copy read-only token"}
                onClick={(e) => {
                    e.preventDefault();
                    copyToClipboard(
                        tokenValue,
                        tokenName,
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
