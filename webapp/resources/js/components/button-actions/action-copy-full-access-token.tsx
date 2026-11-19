import { AppTooltip } from "@/components/app-tooltip";
import { Button } from "@/components/ui/button";
import useCopyToClipboard from "@/hooks/copy-to-clipboard";
import { type GroupDatabaseTokenProps, type UserDatabaseTokenProps } from "@/types";
import { isTokenHash } from "@/lib/utils";
import { CheckIcon, Fingerprint, EyeOff } from "lucide-react";

export default function ButtonCopyFullAccessToken({
    token,
    text = undefined,
}: {
    token: UserDatabaseTokenProps | GroupDatabaseTokenProps | string;
    text?: string;
}) {
    const { copiedText, copyToClipboard } = useCopyToClipboard();

    const tokenValue = typeof token === 'string' ? token : token.full_access_token;
    const tokenName = typeof token === 'string' ? 'token' : token.name;

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
        <AppTooltip text="Copy Full Access Token">
            <Button
                variant="outline"
                size="sm"
                aria-label={text ?? "Copy full access token"}
                onClick={(e) => {
                    e.preventDefault();
                    copyToClipboard(tokenValue, tokenName, 'full');
                }}
            >
                {copiedText[`${typeof token === 'string' ? 'token' : token.name}-full`] ? (
                    <CheckIcon className="h-4 w-4 text-primary dark:text-primary-foreground" />
                ) : (
                    <Fingerprint className="h-4 w-4" />
                )}
                {text && (
                    <span className="ml-2">{text}</span>
                )}
            </Button>
        </AppTooltip>
    );
}
