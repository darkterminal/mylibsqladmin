import { Button } from "@/components/ui/button";
import { CheckIcon, CopyIcon } from "lucide-react";
import { useState } from "react";

export function CopyButton({ value, size }: { value: string, size?: "default" | "sm" | "lg" | "icon" | null | undefined }) {
    const [isCopied, setIsCopied] = useState(false);

    const copyToClipboard = async () => {
        try {
            await navigator.clipboard.writeText(value);
            setIsCopied(true);
            setTimeout(() => setIsCopied(false), 2000); // Reset after 2 seconds
        } catch (err) {
            console.error('Failed to copy text: ', err);
        }
    };

    return (
        <Button
            variant="outline"
            size={size}
            onClick={copyToClipboard}
            aria-label={isCopied ? "Copied!" : "Copy to clipboard"}
        >
            {isCopied ? (
                <CheckIcon className="h-4 w-4 text-primary dark:text-primary-foreground" />
            ) : (
                <CopyIcon className="h-4 w-4" />
            )}
        </Button>
    );
}
