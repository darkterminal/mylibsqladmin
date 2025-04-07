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
                'name' => 'manage-teams',
                'description' => 'Manage team members and their roles'
            ],
            [
                'name' => 'manage-group-databases',
                'description' => 'Create, update, and delete group databases'
            ],
            [
                'name' => 'manage-group-database-tokens',
                'description' => 'Manage access tokens for group databases'
            ],
            [
                'name' => 'manage-database-tokens',
                'description' => 'Manage personal database access tokens'
            ],
            [
                'name' => 'manage-team-groups',
                'description' => 'Manage groups within assigned teams'
            ],
            [
                'name' => 'access-team-databases',
                'description' => 'Access databases through team group tokens'
            ],
            [
                'name' => 'create-teams',
                'description' => 'Create new teams'
            ]
        ];

        foreach ($permissions as $permissionData) {
            Permission::create($permissionData);
        }

        // Create roles with permissions
        $roles = [
            'Super Admin' => [
                'description' => 'Full access to everything',
                'permissions' => [
                    'manage-teams',
                    'create-teams',
                    'manage-group-databases',
                    'manage-group-database-tokens',
                    'manage-database-tokens',
                    'manage-team-groups',
                    'access-team-databases'
                ]
            ],
            'Team Manager' => [
                'description' => 'Manage team members and their roles',
                'permissions' => [
                    'manage-teams',
                    'manage-group-databases',
                    'manage-group-database-tokens',
                    'manage-team-groups'
                ]
            ],
            'Database Maintainer' => [
                'description' => 'Manage group databases',
                'permissions' => [
                    'manage-group-database-tokens',
                    'access-team-databases'
                ]
            ],
            'Member' => [
                'description' => 'Access team databases',
                'permissions' => ['access-team-databases', 'manage-database-tokens']
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
                'password' => $userData['password'],
                'role' => $userData['role']
            ]);

            $role = Role::where('name', $userData['role'])->first();
            $user->roles()->attach($role);
        }
    }
}
