import { databaseType } from "@/lib/utils"
import { Cylinder, File, GitBranch } from "lucide-react"

/**
 * Shared renderer for the is_schema column across the app — one truth for
 * schema / standalone / child-of-<parent> display.
 */
export function DatabaseTypeIcon({
    isSchema,
    className = "mr-2 h-4 w-4 text-primary",
}: {
    isSchema: string | number | boolean
    className?: string
}) {
    const dbType = databaseType(String(isSchema))

    if (dbType === "schema") {
        return <File className={className} />
    }

    if (dbType === "standalone") {
        return <Cylinder className={className} />
    }

    return <GitBranch className={className} />
}

export function DatabaseTypeText({ isSchema }: { isSchema: string | number | boolean }) {
    const dbType = databaseType(String(isSchema))

    if (dbType === "schema") {
        return <span className="text-xs text-muted-foreground">schema</span>
    }

    if (dbType === "standalone") {
        return <span className="text-xs text-muted-foreground">standalone</span>
    }

    return (
        <span className="text-xs text-muted-foreground">
            child of <span className="text-primary">{String(isSchema)}</span>
        </span>
    )
}
