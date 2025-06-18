import { apiFetch } from "@/lib/api";
import { SharedData, Team } from "@/types";
import { router, usePage } from "@inertiajs/react";
import { VariantProps } from "class-variance-authority";
import { BoxIcon } from "lucide-react";
import { AppTooltip } from "../app-tooltip";
import { Button } from "../ui/button";

export default function ButtonOpenDatabaseStudio({
    databaseName,
    text,
    team,
    size = "sm"
}: {
    databaseName: string,
    text?: string,
    team?: Team,
    size?: VariantProps<typeof Button>["size"]
}) {

    const { csrfToken } = usePage<SharedData>().props
    const handleSelectDatabase = async (database: string) => {
        localStorage.setItem('sidebar', 'false');
        if (team) {
            localStorage.setItem('currentTeamId', team.id.toString());
            await apiFetch(route('api.teams.databases', team.id), {
                method: 'GET',
                headers: {
                    'X-CSRF-TOKEN': csrfToken
                }
            });
        }
        router.get(route('database.studio', { database }));
    }

    return (
        <AppTooltip text="Open Database Studio">
            <Button
                variant="outline"
                size={size}
                onClick={() => handleSelectDatabase(databaseName)}
            >
                <BoxIcon className="h-2 w-2" />
                {text && (
                    <span className="ml-1">{text}</span>
                )}
            </Button>
        </AppTooltip>
    )
}
