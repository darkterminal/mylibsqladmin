import { TrashIcon } from "lucide-react";
import { AppTooltip } from "../app-tooltip";
import { Button } from "../ui/button";

export default function ButtonDelete({ handleDelete, text = undefined }: { handleDelete: () => void, text?: string }) {
    return (
        <AppTooltip text={text || "Delete"}>
            <Button
                variant="destructive"
                size="sm"
                onClick={handleDelete}
            >
                <TrashIcon className="h-5 w-5" />
            </Button>
        </AppTooltip>
    )
}
