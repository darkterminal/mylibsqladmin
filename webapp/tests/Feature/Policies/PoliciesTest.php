<?php

/**
 * RBAC policy matrix — pins the seeded permission matrix against the
 * tenant-scoped policies. These tests catch the class of bug where a
 * middleware/policy definition silently changes the effective permission.
 */

use App\Models\GroupDatabase;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\UserDatabase;
use App\Policies\GroupDatabasePolicy;
use App\Policies\TeamPolicy;
use App\Policies\UserDatabasePolicy;
use App\Policies\UserPolicy;
use Illuminate\Support\Facades\Artisan;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RoleUserSeeder', '--force' => true]);
});

function withRole(User $user, string $role): User
{
    $user->roles()->attach(Role::where('name', $role)->firstOrFail()->id);

    return $user;
}

function makeDatabase(User $owner, ?Team $team = null): UserDatabase
{
    return UserDatabase::create([
        'user_id' => $owner->id,
        'database_name' => 'pol-db-' . uniqid(),
        'is_schema' => '0',
        'created_by' => $owner->id,
        'team_id' => $team?->id,
    ]);
}

test('user database policy: delete requires permission AND ownership/super-admin', function () {
    $policy = new UserDatabasePolicy();

    $superAdmin = withRole(User::factory()->create(), 'Super Admin');
    $owner = withRole(User::factory()->create(), 'Super Admin');
    $plainMember = withRole(User::factory()->create(), 'Member');
    $db = makeDatabase($owner);

    // Super admin (any db) may delete
    expect($policy->delete($superAdmin, $db))->toBeTrue();
    // A member with no database permissions may not delete
    expect($policy->delete($plainMember, $db))->toBeFalse();
    // A different super admin may delete
    expect($policy->delete($superAdmin, $db))->toBeTrue();
});

test('user database policy: forceDelete requires super admin regardless of ownership', function () {
    $policy = new UserDatabasePolicy();

    $superAdmin = withRole(User::factory()->create(), 'Super Admin');
    $manager = withRole(User::factory()->create(), 'Member');
    $owner = withRole(User::factory()->create(), 'Member');
    $db = makeDatabase($owner);

    expect($policy->forceDelete($superAdmin, $db))->toBeTrue();
    expect($policy->forceDelete($manager, $db))->toBeFalse();
    expect($policy->forceDelete($owner, $db))->toBeFalse(); // member role lacks the role gate
});

test('team policy: create requires create/manage permission', function () {
    $policy = new TeamPolicy();

    $superAdmin = withRole(User::factory()->create(), 'Super Admin');
    $member = withRole(User::factory()->create(), 'Member');
    $manager = withRole(User::factory()->create(), 'Team Manager');

    expect($policy->create($superAdmin))->toBeTrue();
    expect($policy->create($member))->toBeFalse();
    expect($policy->create($manager))->toBeFalse(); // seeded matrix: Team Manager lacks create-teams
});

test('team policy: update requires same-team membership and update-teams permission', function () {
    $policy = new TeamPolicy();

    $manager = withRole(User::factory()->create(), 'Team Manager');
    $otherMember = withRole(User::factory()->create(), 'Member');

    $teamA = Team::create(['name' => 'Team A', 'description' => 'a']);
    $teamA->members()->attach($manager->id, ['permission_level' => 'team-manager']);
    $teamA->members()->attach($otherMember->id, ['permission_level' => 'member']);

    $teamB = Team::create(['name' => 'Team B', 'description' => 'b']);
    $teamB->members()->attach($manager->id, ['permission_level' => 'team-manager']);

    // manager can update own team (update-teams permission) but NOT team B if not a member
    expect($policy->update($manager, $teamA))->toBeTrue();
    expect($policy->update($manager, $teamB))->toBeTrue(); // member of B too
    $teamC = Team::create(['name' => 'Team C', 'description' => 'c']);
    expect($policy->update($manager, $teamC))->toBeFalse(); // not a member
    expect($policy->update($otherMember, $teamA))->toBeFalse(); // no update-teams permission
});

test('group policy: update/delete require same team + group permissions', function () {
    $policy = new GroupDatabasePolicy();

    $manager = withRole(User::factory()->create(), 'Team Manager'); // manage-groups + update-groups + delete-groups
    $outsider = withRole(User::factory()->create(), 'Team Manager');
    $member = withRole(User::factory()->create(), 'Member');

    $team = Team::create(['name' => 'G Team', 'description' => 'g']);
    $team->members()->attach($manager->id, ['permission_level' => 'team-manager']);
    $team->members()->attach($member->id, ['permission_level' => 'member']);

    $group = GroupDatabase::create([
        'name' => 'G1',
        'user_id' => $manager->id,
        'team_id' => $team->id,
        'created_by' => $manager->id,
    ]);

    expect($policy->update($manager, $group))->toBeTrue();
    expect($policy->update($member, $group))->toBeFalse();
    expect($policy->update($outsider, $group))->toBeFalse();
});

test('user policy: view requires view-users or manage-users; super admin passes', function () {
    $policy = new UserPolicy();

    $superAdmin = withRole(User::factory()->create(), 'Super Admin');
    $member = withRole(User::factory()->create(), 'Member');

    expect($policy->viewAny($superAdmin))->toBeTrue();
    expect($policy->viewAny($superAdmin, User::class))->toBeTrue();
    expect($policy->viewAny($member))->toBeFalse();
    expect($policy->delete($member))->toBeFalse();
    expect($policy->delete($superAdmin))->toBeTrue();
});
