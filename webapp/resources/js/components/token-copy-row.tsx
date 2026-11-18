import { useState } from "react";
import { Button } from "@/components/ui/button";
import { Label } from "@/components/ui/label";
import { Check, Copy } from "lucide-react";

/**
 * One row of a one-time token reveal panel. Tracks its own copied state
 * (the shared clipboard hook's key composition `${id}-${type}` never
 * matched a bare prefix, so reveal rows keep their own state).
 */
export default function TokenCopyRow({ label, value, codeClassName }: { label: string; value: string; codeClassName?: string }) {
    const [copied, setCopied] = useState(false);

    const copy = async () => {
        try {
            await navigator.clipboard.writeText(value);
            setCopied(true);
            setTimeout(() => setCopied(false), 2000);
        } catch (err) {
            console.error("Failed to copy text: ", err);
        }
    };

    return (
        <div className="flex flex-col gap-1.5">
            <Label>{label}</Label>
            <div className="flex items-stretch gap-2">
                <code className={`min-w-0 flex-1 break-all rounded-md border bg-muted px-2 py-2 font-mono text-[10px] leading-relaxed ${codeClassName ?? ""}`}>
                    {value}
                </code>
                <Button
                    type="button"
                    variant="outline"
                    size="icon"
                    className="shrink-0"
                    aria-label={`Copy ${label}`}
                    onClick={copy}
                >
                    {copied ? <Check className="h-4 w-4 text-primary" /> : <Copy className="h-4 w-4" />}
                </Button>
            </div>
        </div>
    );
}
