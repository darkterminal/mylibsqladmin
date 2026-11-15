<?php

/**
 * Group management HTTP surface — create-only, add/remove databases,
 * group tokens.
 */

use App\Models\GroupDatabase;
use App\Models\GroupDatabaseToken;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\UserDatabase;
use Illuminate\Support\Facades\Artisan;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RoleUserSeeder', '--force' => true]);
});

function groupAdmin(): User
{
    $u = User::factory()->create();
    $u->roles()->attach(Role::where('name', 'Super Admin')->firstOrFail()->id);

    return $u;
}

function memberOf(Team $team, string $roleName = 'Member'): User
{
    $u = User::factory()->create();
    $u->roles()->attach(Role::where('name', $roleName)->firstOrFail()->id);
    $team->members()->attach($u->id, ['permission_level' => strtolower($roleName) === 'member' ? 'member' : str_replace(' ', '-', strtolower($roleName))]);

    return $u;
}

function ownedDatabase(User $owner): UserDatabase
{
    return UserDatabase::create([
        'user_id' => $owner->id,
        'database_name' => 'grp-db-' . uniqid(),
        'is_schema' => '0',
        'created_by' => $owner->id,
    ]);
}

test('a team member can create a group in their own team', function () {
    $admin = groupAdmin();
    $team = Team::create(['name' => 'Group Team', 'description' => 'g']);
    $team->members()->attach($admin->id, ['permission_level' => 'super-admin']);

    $this->actingAs($admin)
        ->postJson('/api/group/create-only', ['name' => 'g-test', 'team_id' => $team->id])
        ->assertStatus(201)
        ->assertJsonPath('success', true);

    expect(GroupDatabase::where('name', 'g-test')->where('team_id', $team->id)->exists())->toBeTrue();
});

test('a non-member cannot create a group in a foreign team', function () {
    $outsider = User::factory()->create();
    $outsider->roles()->attach(Role::where('name', 'Member')->firstOrFail()->id);

    $team = Team::create(['name' => 'Other Team', 'description' => 'x']);

    $this->actingAs($outsider)
        ->postJson('/api/group/create-only', ['name' => 'g-nope', 'team_id' => $team->id])
        ->assertStatus(422);

    expect(GroupDatabase::where('name', 'g-nope')->exists())->toBeFalse();
});

test('remove-from-group detaches only the pivot and never archives the database', function () {
    $manager = memberOf(Team::create(['name' => 'Pivot Team', 'description' => 'x']), 'Team Manager');
    $team = $manager->teams()->firstOrFail();

    $database = ownedDatabase($manager);

    $group = GroupDatabase::create([
        'name' => 'pivot-group',
        'user_id' => $manager->id,
        'team_id' => $team->id,
        'created_by' => $manager->id,
    ]);
    $group->members()->attach($database->id);

    $this->actingAs($manager)
        ->delete("/groups/delete-database/{$database->id}")
        ->assertRedirect();

    expect($group->fresh()->members()->where('database_id', $database->id)->exists())->toBeFalse();
    expect(UserDatabase::find($database->id))->not->toBeNull(); // still exists, not archived
});

test('a team manager can mint a group token for their team group', function () {
    $manager = memberOf(Team::create(['name' => 'Token Team', 'description' => 'x']), 'Team Manager');
    $team = $manager->teams()->firstOrFail();

    $group = GroupDatabase::create([
        'name' => 'token-group',
        'user_id' => $manager->id,
        'team_id' => $team->id,
        'created_by' => $manager->id,
    ]);

    $this->actingAs($manager)
        ->post("/groups/{$group->id}/tokens", [
            'group_id' => $group->id,
            'name' => 'ci-token',
            'expiration' => 30,
        ])
        ->assertRedirect();

    $token = GroupDatabaseToken::where('group_id', $group->id)->firstOrFail();
    expect($token->full_access_token)->toMatch('/^[a-f0-9]{64}$/');
});
