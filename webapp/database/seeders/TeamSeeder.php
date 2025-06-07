<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TeamSeeder extends Seeder
{
    public function run()
    {
        DB::transaction(function () {
            // Clear existing team data
            DB::table('team_user')->delete();
            DB::table('teams')->delete();

            // Get existing users
            $users = [
                'super-admin' => User::where('username', 'superadmin')->first(),
                'team-manager' => User::where('username', 'manager')->first(),
                'database-maintainer' => User::where('username', 'database-maintainer')->first(),
                'member' => User::where('username', 'member')->first(),
            ];

            // Create core teams
            $teams = [
                [
                    'name' => 'Engineering Team',
                    'description' => 'Core software development team',
                    'members' => [
                        ['user' => 'super-admin', 'role' => 'super-admin'],
                        ['user' => 'team-manager', 'role' => 'team-manager'],
                        ['user' => 'database-maintainer', 'role' => 'database-maintainer'],
                        ['user' => 'member', 'role' => 'member']
                    ]
                ],
                [
                    'name' => 'Product Team',
                    'description' => 'Product management and strategy',
                    'members' => [
                        ['user' => 'super-admin', 'role' => 'super-admin'],
                        ['user' => 'team-manager', 'role' => 'database-maintainer'],
                        ['user' => 'member', 'role' => 'member']
                    ]
                ]
            ];

            foreach ($teams as $teamData) {
                $team = Team::create([
                    'name' => $teamData['name'],
                    'description' => $teamData['description']
                ]);

                foreach ($teamData['members'] as $member) {
                    $team->members()->attach($users[$member['user']]->id, [
                        'permission_level' => $member['role']
                    ]);
                }
            }
        });
    }
}
