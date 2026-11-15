import { useEffect, useState } from "react"

const CURRENT_TEAM_KEY = "currentTeamId"

/** The single source of truth for the selected team id in the browser. */
export function getCurrentTeamId(): number | null {
    if (typeof window === "undefined") {
        return null
    }

    const raw = window.localStorage.getItem(CURRENT_TEAM_KEY)

    if (!raw || raw === "null") {
        return null
    }

    const n = Number(raw)

    return Number.isFinite(n) ? n : null
}

export function setCurrentTeamId(teamId: number | null): void {
    if (typeof window === "undefined") {
        return
    }

    window.localStorage.setItem(CURRENT_TEAM_KEY, JSON.stringify(teamId))
}

export function clearCurrentTeamId(): void {
    if (typeof window === "undefined") {
        return
    }

    window.localStorage.removeItem(CURRENT_TEAM_KEY)
}

/**
 * React hook mirroring getCurrentTeamId(). Reads once on mount —
 * team switches go through the app-wide team switcher (Inertia visit),
 * so callers refresh on navigation anyway.
 */
export function useCurrentTeamId(): number | null {
    const [teamId, setTeamId] = useState<number | null>(null)

    useEffect(() => {
        setTeamId(getCurrentTeamId())
    }, [])

    return teamId
}
