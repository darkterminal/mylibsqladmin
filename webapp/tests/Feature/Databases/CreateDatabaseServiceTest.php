<?php

/**
 * SqldService::createDatabase() reports failure by returning false (name
 * containing "archived", sqld admin API failure, or the UserDatabase insert
 * failing). The controller used to ignore that and answer success:true, so a
 * broken sqld silently "succeeded" — nothing in the UI complained and the
 * database simply never appeared. These tests pin the failure contract.
 */

use App\Models\GroupDatabase;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\UserDatabase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RoleUserSeeder', '--force' => true]);
});

function makeGroupAndTeamForCreateTest(User $user): GroupDatabase
{
    $team = Team::create(['name' => 'Create Test Team', 'description' => 't']);

    return GroupDatabase::create([
        'name' => 'create-test-group',
        'user_id' => $user->id,
        'team_id' => $team->id,
        'created_by' => $user->id,
    ]);
}

function createDatabaseSuperAdminForServiceTest(): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'Super Admin')->firstOrFail()->id);

    return $user;
}

test('create database succeeds when the sqld admin API answers 200', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*v1/namespaces/*/create' => Http::response(['ok' => true], 200),
    ]);

    $admin = createDatabaseSuperAdminForServiceTest();
    $group = makeGroupAndTeamForCreateTest($admin);

    $this->actingAs($admin)
        ->postJson('/databases/create', [
            'database' => 'svc-ok-db',
            'isSchema' => false,
            'groupId' => $group->id,
            'teamId' => $group->team_id,
        ])
        ->assertOk()
        ->assertJson(['success' => true]);

    expect(UserDatabase::where('database_name', 'svc-ok-db')->exists())->toBeTrue();
});

test('create database reports failure when the sqld admin API fails', function () {
    Http::preventStrayRequests();
    Http::fake([
        '*v1/namespaces/*/create' => Http::response(['error' => 'sqld unreachable'], 500),
    ]);

    $admin = createDatabaseSuperAdminForServiceTest();
    $group = makeGroupAndTeamForCreateTest($admin);

    $this->actingAs($admin)
        ->postJson('/databases/create', [
            'database' => 'svc-fail-db',
            'isSchema' => false,
            'groupId' => $group->id,
            'teamId' => $group->team_id,
        ])
        ->assertStatus(422)
        ->assertJson(['success' => false]);

    expect(UserDatabase::where('database_name', 'svc-fail-db')->exists())->toBeFalse();
});

test('create database reports failure for a name ending in archived', function () {
    $admin = createDatabaseSuperAdminForServiceTest();
    $group = makeGroupAndTeamForCreateTest($admin);

    $this->actingAs($admin)
        ->postJson('/databases/create', [
            'database' => 'my-archived',
            'isSchema' => false,
            'groupId' => $group->id,
            'teamId' => $group->team_id,
        ])
        ->assertStatus(422)
        ->assertJson(['success' => false]);

    expect(UserDatabase::where('database_name', 'my-archived')->exists())->toBeFalse();
});
