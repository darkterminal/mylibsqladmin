import { router } from "@inertiajs/react";
import { VariantProps } from "class-variance-authority";
import { BoxIcon } from "lucide-react";
import { AppTooltip } from "../app-tooltip";
import { Button } from "../ui/button";

export default function ButtonOpenDatabaseStudio({ databaseName, size = "sm" }: { databaseName: string, size?: VariantProps<typeof Button>["size"] }) {
    return (
        <AppTooltip text="Open Database Studio">
            <Button
                variant="outline"
                size={size}
                onClick={() => router.get(route('dashboard'), { database: databaseName })}
            >
                <BoxIcon className="h-2 w-2" />
            </Button>
        </AppTooltip>
    )
}
