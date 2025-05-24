import { TrashIcon } from "lucide-react";
import { AppTooltip } from "../app-tooltip";
import { Button } from "../ui/button";

export default function ButtonDelete({ handleDelete, text = undefined, disabled = false }: { handleDelete: () => void, text?: string, disabled?: boolean }) {
    return (
        <AppTooltip text={text || "Delete"}>
            <Button
                variant="destructive"
                size="sm"
                onClick={handleDelete}
                disabled={disabled}
            >
                <TrashIcon className="h-5 w-5" />
            </Button>
        </AppTooltip>
    )
}
