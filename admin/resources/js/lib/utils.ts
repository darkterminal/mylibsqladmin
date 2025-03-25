import { MostUsedDatabaseMinimalProps, MostUsedDatabaseProps, type LibSQLDatabases } from "@/types";
import { usePage } from "@inertiajs/react";
import { clsx, type ClassValue } from 'clsx';
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
    const standalone = databases.filter(db => Boolean(db.is_schema) === false);
    const parents = databases.filter(db => Boolean(Number(db.is_schema)) === true);
    const childrenMap = databases.reduce((map, db) => {
        if (Boolean(db.is_schema) !== true || Boolean(db.is_schema) !== false) {
            const parentName = db.is_schema;
            map.set(parentName.toString(), [...(map.get(parentName.toString()) || []), db]);
        }
        return map;
    }, new Map<string, LibSQLDatabases[]>());

    return { standalone, parents, childrenMap };
}

export function databaseType(schema: string) {
    if (Boolean(schema) === false) {
        return 'standalone';
    }

    if (Boolean(Number(schema)) === true) {
        return 'schema';
    }

    if (Boolean(schema) !== true || Boolean(schema) !== false) {
        return schema;
    }
}

export function databaseGroupType(dbs: MostUsedDatabaseProps[] | MostUsedDatabaseMinimalProps[]) {
    const standaloneDatabases = dbs.filter(db => databaseType(db.is_schema) === 'standalone');
    const parentDatabases = dbs.filter(db => databaseType(db.is_schema) === 'schema');
    const childDatabases = dbs.reduce((map, db) => {
        if (databaseType(db.is_schema) !== 'schema' && databaseType(db.is_schema) !== 'standalone') {
            const parentDb = db.is_schema;
            map.set(parentDb.toString(), [...(map.get(parentDb.toString()) || []), db])
        }
        return map;
    }, new Map<string, MostUsedDatabaseProps[] | MostUsedDatabaseMinimalProps[]>());

    return { standaloneDatabases, parentDatabases, childDatabases }
}

export function calculateExpirationDate(createdAt: string, expirationDays: number) {
    const createdDate = new Date(createdAt);
    const expirationDate = new Date(createdDate);
    expirationDate.setDate(createdDate.getDate() + expirationDays);

    if (expirationDate.getTime() < new Date().getTime()) {
        return 'expired';
    }

    return expirationDate.toISOString().slice(0, 10);
}
