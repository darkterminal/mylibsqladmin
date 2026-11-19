import { Badge } from "@/components/ui/badge";
import { Column } from "@/components/ui/data-table/data-table";
import { format } from "date-fns";
import DataTableActions from "./user-actions";

export interface UserDataTable {
    id: number;
    name: string;
    username: string;
    email: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
    /** ISO timestamp of the user's latest user_activity_logs entry, or null when none exists. */
    last_active_at: string | null;
    teams: {
        id: number;
        name: string;
        description: string;
        permission_level: string;
    }[]
    roles: {
        id: number;
        name: string;
        created_at: string;
    }[]
}

const formatDateTime = (value: string) => format(new Date(value), "MMM d, yyyy, h:mm a");
const formatDateShort = (value: string) => format(new Date(value), "MMM d, yyyy");

export const userColumns: Column<UserDataTable>[] = [
    {
        key: "name",
        label: "Full Name",
        render: (user) => {
            return (
                <>
                    <p className="font-medium leading-tight">{user.name}</p>
                    <p className="text-xs text-muted-foreground">@{user.username}</p>
                    <Badge variant={user.is_active ? 'outline' : 'destructive'} className="text-muted-foreground text-xs mt-0.5">{user.is_active ? 'Active' : 'Inactive'}</Badge>
                </>
            )
        }
    },
    {
        key: "email",
        label: "Email",
        render: (user) => {
            return <span>{user.email}</span>
        }
    },
    {
        key: "roles",
        label: "Role",
        render: (user) => {
            return user.roles.map((role) => role.name).join(', ')
        }
    },
    {
        key: "teams",
        label: "Teams",
        render: (user) => {
            return user.teams.map((team) => (
                <Badge key={team.id} variant={'outline'} className="m-1 block max-w-[220px] truncate">{team.name} as a {team.permission_level}</Badge>
            ))
        }
    },
    {
        key: "created_at",
        label: "Created",
        render: (user) => {
            return formatDateShort(user.created_at)
        }
    },
    {
        key: "last_active_at",
        label: "Last Active",
        render: (user) => {
            return user.last_active_at ? formatDateTime(user.last_active_at) : <span className="text-muted-foreground">Never</span>
        }
    },
    {
        key: 'actions',
        label: 'Actions',
        render: (user) => <DataTableActions user={user} />
    }
]
