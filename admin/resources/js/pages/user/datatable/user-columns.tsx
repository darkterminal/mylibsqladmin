import { Badge } from "@/components/ui/badge";
import { Column } from "@/components/ui/data-table/data-table";
import DataTableActions from "./user-actions";

export interface UserDataTable {
    id: number;
    name: string;
    username: string;
    email: string;
    is_active: boolean;
    created_at: string;
    updated_at: string;
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

export const userColumns: Column<UserDataTable>[] = [
    {
        key: "name",
        label: "Full Name",
        render: (user) => {
            return (
                <>
                    <p>{user.name}</p>
                    <Badge variant={user.is_active ? 'outline' : 'destructive'} className="text-muted-foreground text-xs">{user.is_active ? 'Active' : 'Inactive'}</Badge>
                </>
            )
        }
    },
    {
        key: "email",
        label: "Email",
        render: (user) => {
            return <span className="blur-sm hover:blur-none">{user.email}</span>
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
                <Badge key={team.id} variant={'outline'} className="m-1 block">{team.name} as a {team.permission_level}</Badge>
            ))
        }
    },
    {
        key: "created_at",
        label: "Created At",
        render: (user) => {
            return new Date(user.created_at).toLocaleString()
        }
    },
    {
        key: "updated_at",
        label: "Updated At",
        render: (user) => {
            return new Date(user.updated_at).toLocaleString()
        }
    },
    {
        key: 'actions',
        label: 'Actions',
        render: (user) => <DataTableActions user={user} />
    }
]
