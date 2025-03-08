import { createClient, ResultSet } from "@libsql/client/web";
import { useEffect, useMemo, useRef, useState } from "react";

type LibSQLStudioProps = {
    databaseName: string | null;
    clientUrl: string | null;
    authToken: string | undefined;
}

export function LibsqlStudio({ databaseName, clientUrl, authToken }: LibSQLStudioProps) {
    const [isSystemDark, setIsSystemDark] = useState(() =>
        window.matchMedia('(prefers-color-scheme: dark)').matches
    );

    const getInitialTheme = () => {
        const localAppearance = localStorage.getItem("appearance");
        if (localAppearance) return localAppearance as 'light' | 'dark' | 'system';
        return isSystemDark ? 'dark' : 'light';
    };

    const [appearance, setAppearance] = useState<'light' | 'dark' | 'system'>(getInitialTheme);
    const effectiveTheme = appearance === 'system' ? (isSystemDark ? 'dark' : 'light') : appearance;

    useEffect(() => {
        const handleAppearanceChange = (event: CustomEvent<{ appearance: 'light' | 'dark' | 'system' }>) => {
            const newAppearance = event.detail.appearance;
            console.log('Appearance changed:', newAppearance);

            if (newAppearance === 'system') {
                setAppearance(effectiveTheme);
            } else {
                setAppearance(newAppearance);
            }
        };

        window.addEventListener('appearance-changed', handleAppearanceChange as EventListener);

        return () => {
            window.removeEventListener('appearance-changed', handleAppearanceChange as EventListener);
        };
    }, []);

    useEffect(() => {
        localStorage.setItem("appearance", appearance);
    }, [appearance]);

    useEffect(() => {
        document.documentElement.setAttribute('data-theme', effectiveTheme);
        setAppearance(effectiveTheme);
    }, [effectiveTheme]);

    const client = useMemo(() => {
        if (!clientUrl) return null;
        return createClient({
            url: clientUrl,
            authToken: authToken,
        });
    }, [clientUrl, authToken]);

    const iframeRef = useRef<HTMLIFrameElement>(null);

    useEffect(() => {
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        const handler = (e: MediaQueryListEvent) => setIsSystemDark(e.matches);

        mediaQuery.addEventListener('change', handler);
        return () => mediaQuery.removeEventListener('change', handler);
    }, []);

    useEffect(() => {
        if (!client) return;

        const contentWindow = iframeRef.current?.contentWindow;

        if (contentWindow) {
            const handler = (e: MessageEvent<ClientRequest>) => {
                if (e.data.type === "query" && e.data.statement) {
                    client
                        .execute(e.data.statement)
                        .then((r) => {
                            contentWindow.postMessage(
                                {
                                    type: e.data.type,
                                    id: e.data.id,
                                    data: transformRawResult(r),
                                },
                                "*"
                            );
                        })
                        .catch((err) => {
                            contentWindow.postMessage(
                                {
                                    type: e.data.type,
                                    id: e.data.id,
                                    error: (err as Error).message,
                                },
                                "*"
                            );
                        });
                } else if (e.data.type === "transaction" && e.data.statements) {
                    client
                        .batch(e.data.statements, "write")
                        .then((r) => {
                            contentWindow.postMessage(
                                {
                                    type: e.data.type,
                                    id: e.data.id,
                                    data: r.map(transformRawResult),
                                },
                                "*"
                            );
                        })
                        .catch((err) => {
                            contentWindow.postMessage(
                                {
                                    type: e.data.type,
                                    id: e.data.id,
                                    error: (err as Error).message,
                                },
                                "*"
                            );
                        });
                }
            };

            window.addEventListener("message", handler);
            return () => window.removeEventListener("message", handler);
        }
    }, [iframeRef, client]);

    return (
        <iframe
            className="iframe-screen-borderless"
            ref={iframeRef}
            src={`https://libsqlstudio.com/embed/sqlite?name=${databaseName}&theme=${appearance}`}
        />
    )
}

interface ClientRequest {
    type: "query" | "transaction";
    id: number;
    statement?: string;
    statements?: string[];
}

interface ResultHeader {
    name: string;
    displayName: string;
    originalType: string | null;
    type: ColumnType;
}

interface Result {
    rows: Record<string, unknown>[];
    headers: ResultHeader[];
    stat: {
        rowsAffected: number;
        rowsRead: number | null;
        rowsWritten: number | null;
        queryDurationMs: number | null;
    };
    lastInsertRowid?: number;
}

enum ColumnType {
    TEXT = 1,
    INTEGER = 2,
    REAL = 3,
    BLOB = 4,
}

function convertSqliteType(type: string | undefined): ColumnType {
    if (type === undefined) return ColumnType.BLOB;

    type = type.toUpperCase();

    if (type.includes("CHAR")) return ColumnType.TEXT;
    if (type.includes("TEXT")) return ColumnType.TEXT;
    if (type.includes("CLOB")) return ColumnType.TEXT;
    if (type.includes("STRING")) return ColumnType.TEXT;

    if (type.includes("INT")) return ColumnType.INTEGER;

    if (type.includes("BLOB")) return ColumnType.BLOB;

    if (
        type.includes("REAL") ||
        type.includes("DOUBLE") ||
        type.includes("FLOAT")
    )
        return ColumnType.REAL;

    return ColumnType.TEXT;
}

function transformRawResult(raw: ResultSet): Result {
    const headerSet = new Set();

    const headers: ResultHeader[] = raw.columns.map((colName, colIdx) => {
        const colType = raw.columnTypes[colIdx];
        let renameColName = colName;

        for (let i = 0; i < 20; i++) {
            if (!headerSet.has(renameColName)) break;
            renameColName = `__${colName}_${i}`;
        }

        headerSet.add(renameColName);

        return {
            name: renameColName,
            displayName: colName,
            originalType: colType,
            type: convertSqliteType(colType),
        };
    });

    const rows = raw.rows.map((r) =>
        headers.reduce((a, b, idx) => {
            a[b.name] = r[idx];
            return a;
        }, {} as Record<string, unknown>)
    );

    return {
        rows,
        stat: {
            rowsAffected: raw.rowsAffected,
            rowsRead: null,
            rowsWritten: null,
            queryDurationMs: 0,
        },
        headers,
        lastInsertRowid:
            raw.lastInsertRowid === undefined
                ? undefined
                : Number(raw.lastInsertRowid),
    };
}
