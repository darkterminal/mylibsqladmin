import { TrashIcon } from "lucide-react";
import { AppTooltip } from "../app-tooltip";
import { Button } from "../ui/button";

export default function ButtonDeleteGroup({ handleDelete }: { handleDelete: () => void }) {
    return (
        <AppTooltip text="Delete Group">
            <Button
                variant="destructive"
                size="icon"
                onClick={handleDelete}
            >
                <TrashIcon className="h-5 w-5" />
            </Button>
        </AppTooltip>
    )
}
