import { router } from "@inertiajs/react";
import { BoxIcon } from "lucide-react";
import { AppTooltip } from "../app-tooltip";
import { Button } from "../ui/button";

export default function ButtonOpenDatabaseStudio({ databaseName }: { databaseName: string }) {
    return (
        <AppTooltip text="Open Database Studio">
            <Button
                variant="outline"
                size="sm"
                onClick={() => router.get(route('dashboard'), { database: databaseName })}
            >
                <BoxIcon className="h-2 w-2" />
            </Button>
        </AppTooltip>
    )
}
