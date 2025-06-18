import { usePermission } from '@/lib/auth';
import { LucideIcon } from 'lucide-react';

export interface Auth {
    user: User;
    permissions: PermissionsResponse;
}

export interface Permissions {
    abilities: string[];
    role: string;
}

export type PermissionsResponse = Permissions | null;

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
    isAllowed?: boolean | ((permissions: ReturnType<typeof usePermission>) => boolean);
}

export interface Invitation {
    id: number;
    team_id: number;
    email: string;
    token: string;
    inviter_id: number;
    permission_level: string;
    expires_at: string;
}

export interface Configs {
    sqldHost?: string;
    sqldPort?: number | string;
}

export interface SharedData {
    name: string;
    auth: Auth;
    flash: FlashMessageProps;
    databases: LibSQLDatabases[];
    groups: GroupDatabaseProps[];
    configs?: Configs;
    invitation?: Invitation;
    csrfToken: string;
    [key: string]: unknown;
}

export interface FlashMessageProps {
    success?: string;
    error?: string;
    newToken?: UserDatabaseTokenProps;
    [key: string]: unknown;
}

export interface User {
    id: number;
    name: string;
    username: string;
    email: string;
    role: string;
    permissions: string[];
    teams: Team[];
    [key: string]: unknown; // This allows for additional properties...
}

export interface Team {
    id: number;
    name: string;
    description: string;
    [key: string]: unknown;
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
    team_id?: number;
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
    database: LibSQLDatabases;
    user: User;
    groups: {
        id: number;
        name: string;
        team: {
            id: number;
            name: string;
        };
    },
    team: {
        id: number;
        name: string;
    }
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

export enum AccessPermissions {
    MANAGE_TEAMS = 'manage-teams',
    CREATE_TEAMS = 'create-teams',
    MANAGE_GROUP_DATABASES = 'manage-group-databases',
    MANAGE_GROUP_DATABASE_TOKENS = 'manage-group-database-tokens',
    MANAGE_DATABASE_TOKENS = 'manage-database-tokens',
    MANAGE_TEAM_GROUPS = 'manage-team-groups',
    ACCESS_TEAM_DATABASES = 'access-team-databases'
}

export interface Member {
    id: number;
    name: string;
    email: string;
    role: string;
    [key: string]: unknown;
}

export type MemberForm = {
    name: string;
    email: string;
    role: string;
}

export interface TeamDatabase {
    id: number
    name: string
    type: string
    lastActivity: string
}

export interface Group extends GroupOnly {
    databases: TeamDatabase[]
}

export interface RecentActivity {
    id: number
    user: string
    action: string
    database: string
    time: string
}

export interface PendingInvitationMember {
    id: number
    name: string
    email: string
    inviter: string
    expires_at: string
    permission_level: string
    sent_at: string
}

export interface Team extends TeamOnly {
    members: number
    groups: Group[]
    team_members: Member[]
    pending_invitations: PendingInvitationMember[]
    recentActivity: RecentActivity[]
}

export interface TeamOnly {
    id: number
    name: string
    description: string
}

export interface GroupOnly {
    id: number
    name: string
}

export interface TeamMembers extends TeamOnly {
    members: Member[]
    pending_invitations: PendingInvitationMember[]
}

export interface TeamCardProps {
    team: Team
    isCurrent?: boolean
    totalTeams?: number
}

export type TeamForm = {
    name: string
    description: string
}

export type PaginatedResults<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: {
        url: string | null;
        label: string;
        active: boolean;
    }[];
};

interface Coordinates {
    lat: number | null;
    lon: number | null;
}

interface Metadata {
    ip: string;
    device: string;
    country: string;
    city: string;
    region?: string;
    coordinates?: Coordinates;
    isp?: string;
}

export enum ActivityType {
    DATABASE_STUDIO_ACTIVITY = 'database_studio_activity',
    LOGIN = "login",
    LOGOUT = "logout",
    PROFILE_UPDATE = "profile_update",
    PASSWORD_UPDATE = "password_update",
    PASSWORD_RESET = "password_reset",
    PASSWORD_RESET_REQUEST = "password_reset_request",
    DATABASE_CREATE = "database_create",
    DATABASE_DELETE = "database_delete",
    DATABASE_UPDATE = "database_update",
    DATABASE_TOKEN_CREATE = "database_token_create",
    DATABASE_TOKEN_DELETE = "database_token_delete",
    DATABASE_TOKEN_UPDATE = "database_token_update",
    GROUP_DATABASE_CREATE = "group_database_create",
    GROUP_DATABASE_DELETE = "group_database_delete",
    GROUP_DATABASE_UPDATE = "group_database_update",
    GROUP_DATABASE_TOKEN_CREATE = "group_database_token_create",
    GROUP_DATABASE_TOKEN_DELETE = "group_database_token_delete",
    GROUP_DATABASE_TOKEN_UPDATE = "group_database_token_update",
    TEAM_CREATE = "team_create",
    TEAM_DELETE = "team_delete",
    TEAM_UPDATE = "team_update",
    TEAM_MEMBER_CREATE = "team_member_create",
    TEAM_MEMBER_DELETE = "team_member_delete",
    TEAM_MEMBER_UPDATE = "team_member_update",
    USER_CREATE = "user_create",
    USER_DELETE = "user_delete",
    USER_UPDATE = "user_update",
    USER_RESTORE = "user_restore",
    USER_DEACTIVATE = "user_deactivate",
    USER_REACTIVATE = "user_reactivate",
    USER_FORCE_DELETE = "user_force_delete",
    ROLE_CREATE = "role_create",
    ROLE_DELETE = "role_delete",
    ROLE_UPDATE = "role_update",
}

export interface ActivityLog {
    type: ActivityType;
    user_id: number;
    updated_at: string;
    metadata: Metadata;
    id: number;
    description: string;
    timestamp: string;
    created_at: string;
    user: User;
}
