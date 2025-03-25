import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
}

export interface BreadcrumbItem {
    title: string;
    href: string;
}

export interface NavGroup {
    title: string;
    items: NavItem[];
}

export interface NavItem {
    title: string;
    url: string;
    icon?: LucideIcon | null;
    isActive?: boolean;
}

export interface SharedData {
    name: string;
    quote: { message: string; author: string };
    auth: Auth;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    email: string;
    avatar?: string;
    email_verified_at: string | null;
    created_at: string;
    updated_at: string;
    [key: string]: unknown; // This allows for additional properties...
}

export interface LibSQLDatabases {
    user_id: number;
    database_name: string;
    is_schema: boolean | string;
}

export interface TopQueryProps {
    rows_written: number;
    rows_read: number;
    query: string;
}

export interface SlowestQueryProps {
    elapsed_ms: number;
    query: string;
    rows_written: number;
    rows_read: number;
}

export interface QueriesInMatrictsStatsProps {
    query: string;
    count: number;
    elapsed_ms: number;
    rows_written: number;
    rows_read: number;
}

export interface QueriesInMatrictsProps {
    id: string;
    created_at: number;
    count: number;
    stats: QueriesInMatrictsStatsProps[];
    elapsed: any;
}

export interface QueryMetrics {
    id: number;
    name: string;
    rows_read_count: number;
    rows_written_count: number;
    storage_bytes_used: number;
    query_count: number;
    elapsed_ms: number;
    write_requests_delegated: number;
    replication_index: number;
    embedded_replica_frames_replicated: number;
    queries: QueriesInMatrictsProps | undefined | null;
    top_queries: TopQueryProps[];
    slowest_queries: SlowestQueryProps[];
    created_at: string;
}

export interface MostUsedDatabaseProps {
    query_metrics_id: number;
    database_id: number;
    database_name: string;
    is_schema: string;
    query_metrics_sum_query_count: number;
    query_metrics_count: number | null;
    created_at: string;
}

export interface MostUsedDatabaseMinimalProps {
    database_id: number;
    database_name: string;
    is_schema: string;
}

export interface DatabaseStatsChangeProps {
    type: 'query' | 'transaction',
    statement: string,
    databaseName: string
}

export type AppearanceStateChangeProps = { appearance: 'light' | 'dark' | 'system' }

export interface OpenModalStateChangeProps {
    isModalOpen: boolean,
    parentDatabase: string
}

export interface UserDatabaseTokenProps {
    id: number;
    user_id: number;
    database_id: number;
    name: string;
    full_access_token: string;
    read_only_token: string;
    expiration_day: number;
    database: LibSQLDatabases
}

export interface DatabaseInGroupProps {
    id: number;
    database_name: string;
    is_schema: string
}

export interface GroupDatabaseTokenProps {
    id: number;
    group_id: number;
    database_id: number;
    name: string;
    full_access_token: string;
    read_only_token: string;
    expiration_day: number;
    created_at: string
}

export interface GroupDatabaseProps {
    id: number;
    name: string;
    members_count: number;
    created_at: string;
    user: {
        name: string;
    };
    members: DatabaseInGroupProps[];
    database_tokens: UserDatabaseTokenProps[];
    has_token: boolean;
    group_token: GroupDatabaseTokenProps
}
