import { usePage } from "@inertiajs/react";
import type { SharedData } from "@/types";

/**
 * Timezone & datetime helpers.
 *
 * All timestamps produced by the server are UTC (naive `YYYY-MM-DD HH:mm:ss`
 * strings or ISO). Displaying them must happen in the installation's
 * effective display timezone — supplied by the server via shared Inertia
 * props (`configs.timezone`), never the browser's timezone.
 */

export type DisplayStyle = "datetime" | "date" | "time";

/** Effective display timezone for this installation. */
export function useDisplayTimezone(): string {
    const props = usePage().props as SharedData;
    const tz = (props as { configs?: { timezone?: string } })?.configs?.timezone;
    return typeof tz === "string" && tz !== "" ? tz : "UTC";
}

/** Parse a server-produced timestamp as the UTC instant it represents. */
export function parseServerDate(value: string | null | undefined): Date | null {
    if (!value) return null;
    const iso = value.includes("T") ? value : value.trim().replace(" ", "T") + "Z";
    const d = new Date(iso);
    return Number.isNaN(d.getTime()) ? null : d;
}

/** Format a server timestamp in the effective display timezone. */
export function formatInTimezone(value: string, tz: string, style: DisplayStyle = "datetime"): string {
    const d = parseServerDate(value);
    if (!d) return "—";

    const options: Intl.DateTimeFormatOptions =
        style === "date"
            ? { year: "numeric", month: "short", day: "numeric" }
            : style === "time"
              ? { hour: "numeric", minute: "2-digit" }
              : { year: "numeric", month: "short", day: "numeric", hour: "numeric", minute: "2-digit" };

    return new Intl.DateTimeFormat("en-US", { timeZone: tz, ...options }).format(d);
}

/** Format a server timestamp with the offset, e.g. "Nov 15, 2026, 7:48 PM GMT+7". */
export function formatFullInTimezone(value: string, tz: string): string {
    const d = parseServerDate(value);
    if (!d) return "—";

    return new Intl.DateTimeFormat("en-US", {
        timeZone: tz,
        year: "numeric",
        month: "short",
        day: "numeric",
        hour: "numeric",
        minute: "2-digit",
        timeZoneName: "shortOffset",
    }).format(d);
}
