import { SharedData } from "@/types";
import { usePage } from "@inertiajs/react";

type PermissionAttributes =
    'manage-teams' |
    'create-teams' |
    'manage-group-databases' |
    'manage-group-database-tokens' |
    'manage-database-tokens' |
    'manage-team-groups' |
    'access-team-databases';

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
