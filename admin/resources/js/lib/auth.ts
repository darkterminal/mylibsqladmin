import { SharedData } from "@/types";
import { usePage } from "@inertiajs/react";

type PermissionAttributes =
    'manage-databases' |
    'view-databases' |
    'create-databases' |
    'update-databases' |
    'delete-databases' |
    'manage-database-tokens' |
    'view-database-tokens' |
    'create-database-tokens' |
    'update-database-tokens' |
    'delete-database-tokens' |
    'manage-teams' |
    'view-teams' |
    'create-teams' |
    'update-teams' |
    'delete-teams' |
    'manage-groups' |
    'view-groups' |
    'create-groups' |
    'update-groups' |
    'delete-groups' |
    'manage-group-tokens' |
    'view-group-tokens' |
    'create-group-tokens' |
    'update-group-tokens' |
    'delete-group-tokens' |
    'manage-team-members' |
    'view-team-members' |
    'create-team-members' |
    'update-team-members' |
    'delete-team-members' |
    'manage-users' |
    'view-users' |
    'create-users' |
    'update-users' |
    'delete-users' |
    'manage-roles' |
    'view-roles' |
    'create-roles' |
    'update-roles' |
    'delete-roles';

type RoleAttributes =
    'Super Admin' |
    'Team Manager' |
    'Database Maintainer' |
    'Member';

// Check basic permissions
export function usePermission() {
    const { auth } = usePage<SharedData>().props;

    return {
        can: (permission: PermissionAttributes) => {
            return auth?.permissions?.abilities.includes(permission) ?? false;
        },
        hasRole: (role: RoleAttributes) => {
            return auth?.user.role === role;
        },
        hasAnyRole: (...roles: RoleAttributes[]) => {
            return roles.includes(auth?.user.role as RoleAttributes) ?? false;
        }
    }
}
