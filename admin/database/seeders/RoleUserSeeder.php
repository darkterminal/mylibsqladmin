<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;
use App\Models\User;
use App\Models\Permission;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class RoleUserSeeder extends Seeder
{
    public function run()
    {
        DB::table('role_user')->delete();
        DB::table('permission_role')->delete();
        User::query()->delete();
        Role::query()->delete();
        Permission::query()->delete();

        if (DB::getDriverName() === 'sqlite') {
            DB::statement('DELETE FROM sqlite_sequence WHERE name IN ("users", "roles", "permissions", "role_user", "permission_role")');
        }

        // Create permissions
        $permissions = [
            [
                'name' => 'manage-databases',
                'description' => 'Manage all databases'
            ],
            [
                'name' => 'view-databases',
                'description' => 'View all databases'
            ],
            [
                'name' => 'create-databases',
                'description' => 'Create databases'
            ],
            [
                'name' => 'update-databases',
                'description' => 'Update databases (Not Implemented)'
            ],
            [
                'name' => 'delete-databases',
                'description' => 'Delete databases'
            ],
            [
                'name' => 'manage-database-tokens',
                'description' => 'Manage all database tokens'
            ],
            [
                'name' => 'view-database-tokens',
                'description' => 'View all database tokens'
            ],
            [
                'name' => 'create-database-tokens',
                'description' => 'Create database tokens'
            ],
            [
                'name' => 'update-database-tokens',
                'description' => 'Update database tokens'
            ],
            [
                'name' => 'delete-database-tokens',
                'description' => 'Delete database tokens'
            ],
            [
                'name' => 'manage-teams',
                'description' => 'Manage all teams'
            ],
            [
                'name' => 'view-teams',
                'description' => 'View all teams'
            ],
            [
                'name' => 'create-teams',
                'description' => 'Create teams'
            ],
            [
                'name' => 'update-teams',
                'description' => 'Update teams'
            ],
            [
                'name' => 'delete-teams',
                'description' => 'Delete teams'
            ],
            [
                'name' => 'manage-groups',
                'description' => 'Manage all groups'
            ],
            [
                'name' => 'view-groups',
                'description' => 'View all groups'
            ],
            [
                'name' => 'create-groups',
                'description' => 'Create groups'
            ],
            [
                'name' => 'update-groups',
                'description' => 'Update groups'
            ],
            [
                'name' => 'delete-groups',
                'description' => 'Delete groups'
            ],
            [
                'name' => 'manage-group-tokens',
                'description' => 'Manage all group tokens'
            ],
            [
                'name' => 'view-group-tokens',
                'description' => 'View all group tokens'
            ],
            [
                'name' => 'create-group-tokens',
                'description' => 'Create group tokens'
            ],
            [
                'name' => 'update-group-tokens',
                'description' => 'Update group tokens'
            ],
            [
                'name' => 'delete-group-tokens',
                'description' => 'Delete group tokens'
            ],
            [
                'name' => 'manage-team-members',
                'description' => 'Manage all team members'
            ],
            [
                'name' => 'view-team-members',
                'description' => 'View all team members'
            ],
            [
                'name' => 'create-team-members',
                'description' => 'Create team members'
            ],
            [
                'name' => 'update-team-members',
                'description' => 'Update team members'
            ],
            [
                'name' => 'delete-team-members',
                'description' => 'Delete team members'
            ],
            [
                'name' => 'manage-users',
                'description' => 'Manage all users'
            ],
            [
                'name' => 'view-users',
                'description' => 'View all users'
            ],
            [
                'name' => 'create-users',
                'description' => 'Create users'
            ],
            [
                'name' => 'update-users',
                'description' => 'Update users'
            ],
            [
                'name' => 'delete-users',
                'description' => 'Delete users'
            ],
            [
                'name' => 'manage-roles',
                'description' => 'Manage all roles'
            ],
            [
                'name' => 'view-roles',
                'description' => 'View all roles'
            ],
            [
                'name' => 'create-roles',
                'description' => 'Create roles'
            ],
            [
                'name' => 'update-roles',
                'description' => 'Update roles'
            ],
            [
                'name' => 'delete-roles',
                'description' => 'Delete roles'
            ],
        ];

        foreach ($permissions as $permissionData) {
            Permission::create($permissionData);
        }

        // Create roles with permissions
        $roles = [
            'Super Admin' => [
                'description' => 'Full access to everything',
                'permissions' => [
                    // Super Admin has all permissions
                    'manage-databases',
                    'view-databases',
                    'create-databases',
                    'update-databases',
                    'delete-databases',
                    'manage-database-tokens',
                    'view-database-tokens',
                    'create-database-tokens',
                    'update-database-tokens',
                    'delete-database-tokens',
                    'manage-teams',
                    'view-teams',
                    'create-teams',
                    'update-teams',
                    'delete-teams',
                    'manage-groups',
                    'view-groups',
                    'create-groups',
                    'update-groups',
                    'delete-groups',
                    'manage-group-tokens',
                    'view-group-tokens',
                    'create-group-tokens',
                    'update-group-tokens',
                    'delete-group-tokens',
                    'manage-team-members',
                    'view-team-members',
                    'create-team-members',
                    'update-team-members',
                    'delete-team-members',
                    'manage-users',
                    'view-users',
                    'create-users',
                    'update-users',
                    'delete-users',
                    'manage-roles',
                    'view-roles',
                    'create-roles',
                    'update-roles',
                    'delete-roles'
                ]
            ],
            'Team Manager' => [
                'description' => 'Manage all related team permissions',
                'permissions' => [
                    // Team managers can manage databases within their team scope
                    'view-databases',
                    'create-databases',
                    // Can view and manage tokens for team databases
                    'view-database-tokens',
                    // Can view team details
                    'view-teams',
                    // Can manage groups within their team
                    'manage-groups',
                    'view-groups',
                    'create-groups',
                    'update-groups',
                    'delete-groups',
                    // Can manage group tokens
                    'manage-group-tokens',
                    'view-group-tokens',
                    'create-group-tokens',
                    'update-group-tokens',
                    'delete-group-tokens',
                    // Can manage team members
                    'manage-team-members',
                    'view-team-members',
                    'create-team-members',
                    'update-team-members',
                    'delete-team-members',
                    // Can view users
                    'view-users',
                    // Can view roles
                    'view-roles'
                ]
            ],
            'Database Maintainer' => [
                'description' => 'Manage all related database permissions',
                'permissions' => [
                    // Database maintainers can manage specific databases
                    'view-databases',
                    // Can manage tokens for their databases
                    'manage-database-tokens',
                    'view-database-tokens',
                    'create-database-tokens',
                    'update-database-tokens',
                    'delete-database-tokens',
                    // Can view team and group information
                    'view-teams',
                    'view-groups',
                    'view-group-tokens',
                    // Can view team members
                    'view-team-members'
                ]
            ],
            'Member' => [
                'description' => 'Manage all related team permissions and database permissions that granted to member',
                'permissions' => [
                    // Members have limited view access
                    'view-databases',
                    'view-database-tokens',
                    'view-teams',
                    'view-groups',
                    'view-group-tokens',
                    'view-team-members'
                ]
            ]
        ];

        foreach ($roles as $name => $data) {
            $role = Role::create([
                'name' => $name,
                'description' => $data['description']
            ]);

            $role->permissions()->sync(
                Permission::whereIn('name', $data['permissions'])->pluck('id')
            );
        }

        // Create users with roles
        $users = [
            [
                'name' => 'Imam Ali Mustofa',
                'username' => 'darkterminal',
                'email' => 'superadmin@mylibsqladmin.oss',
                'password' => Hash::make('dimonggoin123'),
                'role' => 'Super Admin'
            ],
            [
                'name' => 'Roy Alkina',
                'username' => 'royalkina',
                'email' => 'manager@mylibsqladmin.oss',
                'password' => Hash::make('dimonggoin123'),
                'role' => 'Team Manager'
            ],
            [
                'name' => 'Jane Doe',
                'username' => 'janedoe',
                'email' => 'database_maintainer@mylibsqladmin.oss',
                'password' => Hash::make('dimonggoin123'),
                'role' => 'Database Maintainer'
            ],
            [
                'name' => 'John Doe',
                'username' => 'jdoe',
                'email' => 'member@mylibsqladmin.oss',
                'password' => Hash::make('dimonggoin123'),
                'role' => 'Member'
            ],
        ];

        foreach ($users as $userData) {
            $user = User::create([
                'name' => $userData['name'],
                'username' => $userData['username'],
                'email' => $userData['email'],
                'password' => $userData['password']
            ]);

            $role = Role::where('name', $userData['role'])->first();
            $user->roles()->attach($role);
        }
    }
}
