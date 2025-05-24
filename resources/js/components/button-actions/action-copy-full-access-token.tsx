import { AppTooltip } from "@/components/app-tooltip";
import { Button } from "@/components/ui/button";
import useCopyToClipboard from "@/hooks/copy-to-clipboard";
import { type GroupDatabaseTokenProps, type UserDatabaseTokenProps } from "@/types";
import { CheckIcon, Fingerprint } from "lucide-react";

export default function ButtonCopyFullAccessToken({
    token,
    text = undefined,
}: {
    token: UserDatabaseTokenProps | GroupDatabaseTokenProps | string;
    text?: string;
}) {
    const { copiedText, copyToClipboard } = useCopyToClipboard();

    return (
        <AppTooltip text="Copy Full Access Token">
            <Button
                variant="outline"
                size="sm"
                onClick={(e) => {
                    e.preventDefault();
                    copyToClipboard(typeof token === 'string' ? token : token.full_access_token, typeof token === 'string' ? 'token' : token.name, 'full');
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
