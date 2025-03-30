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
                'superadmin' => User::where('username', 'darkterminal')->first(),
                'manager' => User::where('username', 'royalkina')->first(),
                'member' => User::where('username', 'jdoe')->first(),
                'maintainer' => User::where('username', 'janedoe')->first(),
            ];

            // Create core teams
            $teams = [
                [
                    'name' => 'Engineering Team',
                    'description' => 'Core software development team',
                    'members' => [
                        ['user' => 'superadmin', 'role' => 'admin'],
                        ['user' => 'manager', 'role' => 'admin'],
                        ['user' => 'maintainer', 'role' => 'maintainer'],
                        ['user' => 'member', 'role' => 'member']
                    ]
                ],
                [
                    'name' => 'Product Team',
                    'description' => 'Product management and strategy',
                    'members' => [
                        ['user' => 'superadmin', 'role' => 'admin'],
                        ['user' => 'manager', 'role' => 'maintainer'],
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
