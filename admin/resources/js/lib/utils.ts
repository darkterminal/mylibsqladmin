import { type LibSQLDatabases } from "@/types";
import { usePage } from "@inertiajs/react";
import { type ClassValue, clsx } from 'clsx';
import { twMerge } from 'tailwind-merge';

export function cn(...inputs: ClassValue[]) {
    return twMerge(clsx(inputs));
}

export function getQuery(key: any = null, fallback: any = null) {
    let query = (usePage().props.ziggy as Record<any, any>).query;
    query = query.length == 0 ? {} : query; // cuz when empty Laravel return an array - can change it in HandleInertiaRequests.php!

    if (key) {
        return query.hasOwnProperty(key) ? query[key] : fallback;
    }

    return query;
}

export function formatBytes(bytes: number, decimals = 2) {
    if (bytes === 0) return "0 Bytes"

    const k = 1024
    const dm = decimals < 0 ? 0 : decimals
    const sizes = ["Bytes", "KB", "MB", "GB", "TB", "PB", "EB", "ZB", "YB"]

    const i = Math.floor(Math.log(bytes) / Math.log(k))

    return Number.parseFloat((bytes / Math.pow(k, i)).toFixed(dm)) + " " + sizes[i]
}

export function groupDatabases(databases: LibSQLDatabases[]) {
    const parents = databases.filter(db => db.is_schema === '1');
    const childrenMap = databases.reduce((map, db) => {
        if (db.is_schema !== '1' && db.is_schema !== '0') {
            const parentName = db.is_schema;
            map.set(parentName.toString(), [...(map.get(parentName.toString()) || []), db]);
        }
        return map;
    }, new Map<string, LibSQLDatabases[]>());
    const standalone = databases.filter(db => db.is_schema === '0');

    return { standalone, parents, childrenMap };
}
