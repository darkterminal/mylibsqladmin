<?php

/**
 * Pin the four RBAC boundary decisions (D1-D4) so they cannot silently drift.
 */

use App\Models\GroupDatabase;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\UserDatabase;
use App\Models\GroupDatabaseToken;
use App\Models\UserDatabaseToken;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RoleUserSeeder', '--force' => true]);
});

function withRoleD4(User $user, string $role): User
{
    $user->roles()->attach(Role::where('name', $role)->firstOrFail()->id);

    return $user;
}

/**
 * D1: deleting a token requires ownership of the underlying database (or team
 * membership / manage-users / super-admin) — not just the token permission.
 */
test('a database maintainer cannot delete a token for a database they cannot manage', function () {
    $owner = withRoleD4(User::factory()->create(), 'Super Admin');
    $outsider = withRoleD4(User::factory()->create(), 'Database Maintainer');

    $team = Team::create(['name' => 'D1 Team', 'description' => 'x']);
    // owner (admin) belongs; outsider does NOT belong to the owning team
    $team->members()->attach($owner->id, ['permission_level' => 'super-admin']);

    $database = UserDatabase::create([
        'user_id' => $owner->id,
        'team_id' => $team->id,
        'database_name' => 'd1-db-' . uniqid(),
        'is_schema' => '0',
        'created_by' => $owner->id,
    ]);

    $token = UserDatabaseToken::create([
        'user_id' => $owner->id,
        'database_id' => $database->id,
        'name' => 'd1-token',
        'full_access_token' => hash('sha256', Str::random(32)),
        'read_only_token' => hash('sha256', Str::random(32)),
        'expiration_day' => 30,
        'created_by' => $owner->id,
    ]);

    $this->actingAs($outsider)->delete("/tokens/delete/{$token->id}")->assertForbidden();
    expect(UserDatabaseToken::find($token->id))->not->toBeNull();

    // the same maintainer mints/deletes within their own scope
    $this->actingAs($owner)->delete("/tokens/delete/{$token->id}")->assertRedirect();
});

/**
 * D2: minting a group token requires team membership + manage-group-tokens
 * (or manage-users / super-admin); the global bypass is gone.
 */
test('a database maintainer from another team cannot mint a group token', function () {
    $managerA = withRoleD4(User::factory()->create(), 'Database Maintainer');

    $teamA = Team::create(['name' => 'D2 Team A', 'description' => 'a']);
    $teamA->members()->attach($managerA->id, ['permission_level' => 'database-maintainer']);

    // Team B owns the group; maintainer A is not a member of team B
    $teamB = Team::create(['name' => 'D2 Team B', 'description' => 'b']);

    $group = GroupDatabase::create([
        'name' => 'd2-group',
        'user_id' => $managerA->id,
        'team_id' => $teamB->id,
        'created_by' => $managerA->id,
    ]);

    $this->actingAs($managerA)
        ->post("/groups/{$group->id}/tokens", [
            'group_id' => $group->id,
            'name' => 'x-token',
            'expiration' => 30,
        ])
        ->assertForbidden();
});

test('a team manager in the group team can mint the group token', function () {
    $manager = withRoleD4(User::factory()->create(), 'Team Manager');

    $team = Team::create(['name' => 'D2 Own Team', 'description' => 'a']);
    $team->members()->attach($manager->id, ['permission_level' => 'database-maintainer']);

    $group = GroupDatabase::create([
        'name' => 'd2-own-group',
        'user_id' => $manager->id,
        'team_id' => $team->id,
        'created_by' => $manager->id,
    ]);

    $this->actingAs($manager)
        ->post("/groups/{$group->id}/tokens", [
            'group_id' => $group->id,
            'name' => 'own-token',
            'expiration' => 30,
        ])
        ->assertRedirect();

    expect(GroupDatabaseToken::where('group_id', $group->id)->count())->toBe(1);
});

/**
 * D3: inviting is member administration — members of the team with
 * manage-team-members may invite; outlanders may not.
 */
test('a member of another team cannot invite into a team they do not belong to', function () {
    $outsider = withRoleD4(User::factory()->create(), 'Member');

    $team = Team::create(['name' => 'D3 Team', 'description' => 'd']);

    $this->actingAs($outsider)
        ->post("/teams/{$team->id}/invitations", [
            'email' => 'x@example.com',
            'role' => 'member',
        ])
        ->assertForbidden();
});

/**
 * D4: /activities is limited to members of the team being logged.
 */
test('a non-member cannot write an activity log against another team', function () {
    $outsider = withRoleD4(User::factory()->create(), 'Member');

    $team = Team::create(['name' => 'D4 Team', 'description' => 'd']);
    $owner = User::factory()->create();
    $team->members()->attach($owner->id, ['permission_level' => 'team-manager']);

    $database = UserDatabase::create([
        'user_id' => $owner->id,
        'team_id' => $team->id,
        'database_name' => 'd4-db-' . uniqid(),
        'is_schema' => '0',
        'created_by' => $owner->id,
    ]);

    $this->actingAs($outsider)->postJson('/activities', [
        'team_id' => $team->id,
        'database_name' => $database->database_name,
        'query' => 'SELECT 1',
    ])->assertForbidden();
});

test('a member can write an activity log against their own team', function () {
    $member = withRoleD4(User::factory()->create(), 'Member');

    $team = Team::create(['name' => 'D4 Own Team', 'description' => 'd']);
    $team->members()->attach($member->id, ['permission_level' => 'member']);

    $database = UserDatabase::create([
        'user_id' => $member->id,
        'team_id' => $team->id,
        'database_name' => 'd4-own-db-' . uniqid(),
        'is_schema' => '0',
        'created_by' => $member->id,
    ]);

    $this->actingAs($member)->postJson('/activities', [
        'team_id' => $team->id,
        'database_name' => $database->database_name,
        'query' => 'SELECT 1',
    ])->assertStatus(201);
});
