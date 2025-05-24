import { cn } from "@/lib/utils";
import { Group, Team } from "@/types";
import { ChevronDown, ChevronRight, Cylinder, File, FolderClosed, GitBranch } from "lucide-react";
import { useState } from "react";
import ButtonOpenDatabaseStudio from "../button-actions/action-open-database-studio";
import { Badge } from "../ui/badge";

export function GroupTree({ groups, team }: { groups: Group[], team?: Team }) {
    const [expandedGroups, setExpandedGroups] = useState<{
        [groupId: number]: boolean;
    }>(
        groups.reduce<{ [groupId: number]: boolean }>((acc, group) => {
            acc[group.id] = group.databases.length > 0
            return acc
        }, {}),
    )

    const toggleGroup = (groupId: number) => {
        setExpandedGroups((prev) => ({
            ...prev,
            [groupId]: !prev[groupId],
        }))
    }

    return (
        <div className="space-y-1">
            {groups.map((group) => (
                <div key={group.id} className="rounded-md overflow-hidden">
                    <button
                        onClick={() => toggleGroup(group.id)}
                        className="w-full flex items-center text-left p-2 hover:bg-muted/80 rounded-md transition-colors"
                    >
                        {expandedGroups[group.id] ? (
                            <ChevronDown className="h-4 w-4 mr-1 text-muted-foreground shrink-0" />
                        ) : (
                            <ChevronRight className="h-4 w-4 mr-1 text-muted-foreground shrink-0" />
                        )}
                        <FolderClosed className="h-4 w-4 mr-2 text-muted-foreground shrink-0" />
                        <span className="font-medium">{group.name}</span>
                        <Badge variant="outline" className="ml-2 text-xs">
                            {group.databases.length}
                        </Badge>
                    </button>

                    <div
                        className={cn(
                            "pl-7 space-y-1 mt-1 overflow-hidden transition-all duration-200",
                            expandedGroups[group.id] ? "max-h-96" : "max-h-0",
                        )}
                    >
                        {group.databases.map((db) => (
                            <div
                                key={db.id}
                                className="flex items-center justify-between text-sm p-2 rounded-md hover:bg-muted/80 transition-colors"
                            >
                                <div className="flex items-center">
                                    {db.type === "standalone" ? <Cylinder className="h-4 w-4 mr-2 text-muted-foreground shrink-0" /> : db.type == "schema" ? <File className="h-4 w-4 mr-2 text-muted-foreground shrink-0" /> : <GitBranch className="h-4 w-4 mr-2 text-muted-foreground shrink-0" />}
                                    <span>{db.name}</span>
                                </div>
                                <div className="flex items-center gap-2">
                                    <Badge
                                        variant={
                                            db.type === "standalone" ? "default" : db.type === "schema" ? "secondary" : "outline"
                                        }
                                        className="text-xs"
                                    >
                                        {db.type}
                                    </Badge>
                                    <ButtonOpenDatabaseStudio databaseName={db.name} team={team} />
                                </div>
                            </div>
                        ))}
                    </div>
                </div>
            ))}
        </div>
    )
}
