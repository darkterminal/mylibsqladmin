<?php

/**
 * Read-side contract for the teams dashboard pages: the prop shape must not
 * drift when the query/decoration code is extracted.
 */

use App\Models\GroupDatabase;
use App\Models\GroupDatabaseToken;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'RoleUserSeeder', '--force' => true]);
});

function dashboardAdmin(): User
{
    $u = User::factory()->create();
    $u->roles()->attach(Role::where('name', 'Super Admin')->firstOrFail()->id);

    return $u;
}

function seededTeamWithDatabase(): array
{
    $admin = dashboardAdmin();

    $team = Team::create(['name' => 'Read Team', 'description' => 'read side']);
    $team->members()->attach($admin->id, ['permission_level' => 'super-admin']);

    $group = GroupDatabase::create([
        'name' => 'read-group',
        'user_id' => $admin->id,
        'team_id' => $team->id,
        'created_by' => $admin->id,
    ]);

    $db = \App\Models\UserDatabase::create([
        'user_id' => $admin->id,
        'database_name' => 'read-db',
        'is_schema' => '0',
        'created_by' => $admin->id,
        'team_id' => $team->id,
    ]);
    $group->members()->attach($db->id);

    return [$admin, $team];
}

test('dashboard teams page renders with expected prop shape', function () {
    [$admin] = seededTeamWithDatabase();

    $this->actingAs($admin)
        ->get('/dashboard/teams')
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard-team')
            ->has('teams', 1)
            ->has('teams.0.id')
            ->has('teams.0.name')
            ->has('teams.0.members')
            ->has('teams.0.team_members', 1)
            ->has('teams.0.groups', 1)
            ->has('teams.0.pending_invitations')
            ->has('teams.0.recentActivity')
            ->has('teams.0.groups.0.databases', 1)
            ->where('teams.0.members', 1)
        );
});

test('archived teams page renders with expected prop shape', function () {
    [$admin] = seededTeamWithDatabase();
    $team = Team::where('name', 'Read Team')->firstOrFail();
    $team->delete();

    $this->actingAs($admin)
        ->get('/teams/archives')
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard-team-archives')
            ->has('teams', 1)
            ->has('teams.0.id')
            ->has('teams.0.name')
            ->has('teams.0.members')
            ->has('teams.0.team_members', 1)
            ->has('teams.0.groups', 1)
            ->has('teams.0.groups.0.databases', 1)
            ->has('teams.0.pending_invitations')
            ->has('teams.0.recentActivity')
        );
});

test('the teams list reports team membership, not database count, in members', function () {
    $admin = dashboardAdmin();

    $team = Team::create(['name' => 'Count Team', 'description' => 'regression']);
    $team->members()->attach($admin->id, ['permission_level' => 'super-admin']);

    $group = GroupDatabase::create([
        'name' => 'count-group',
        'user_id' => $admin->id,
        'team_id' => $team->id,
        'created_by' => $admin->id,
    ]);

    foreach (['count-db-1', 'count-db-2'] as $name) {
        $db = \App\Models\UserDatabase::create([
            'user_id' => $admin->id,
            'database_name' => $name,
            'is_schema' => '0',
            'created_by' => $admin->id,
            'team_id' => $team->id,
        ]);
        $group->members()->attach($db->id);
    }

    $this->actingAs($admin)
        ->get('/dashboard/teams')
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard-team')
            ->has('teams', 1)
            ->where('teams.0.members', 1)   // NOT 2 (database count)
            ->has('teams.0.groups.0.databases', 2)
        );
});

test('a regular member sees only teams they belong to and only granted groups', function () {
    $admin = dashboardAdmin();

    $teamA = Team::create(['name' => 'Member Team A', 'description' => 'a']);
    $member = User::factory()->create();
    $member->roles()->attach(Role::where('name', 'Member')->firstOrFail()->id);
    $teamA->members()->attach($member->id, ['permission_level' => 'member']);

    $teamB = Team::create(['name' => 'Foreign Team B', 'description' => 'b']);
    $teamB->members()->attach($admin->id, ['permission_level' => 'super-admin']);

    $this->actingAs($member)
        ->get('/dashboard/teams')
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard-team')
            ->has('teams', 1)
            ->where('teams.0.name', 'Member Team A')
        );
});
