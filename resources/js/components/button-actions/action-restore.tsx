import { RefreshCcw } from "lucide-react";
import { AppTooltip } from "../app-tooltip";
import { Button } from "../ui/button";

export default function ButtonRestore({ handleRestore, text = undefined }: { handleRestore: () => void, text?: string }) {
    return (
        <AppTooltip text={text || "Restore"}>
            <Button
                variant="outline"
                size="sm"
                onClick={handleRestore}
            >
                <RefreshCcw className="h-5 w-5" />
            </Button>
        </AppTooltip>
    )
}
