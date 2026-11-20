<?php

/**
 * The policy allows a Super Admin to act on any database, but the service
 * layer used to re-scope every lifecycle lookup to auth()->id(). Cross-owner
 * archive/restore/force-delete therefore failed with a generic flash message.
 * Ownership is now passed explicitly from the record the controller authorized.
 */

use App\Models\GroupDatabase;
use App\Models\Permission;
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

function makeDatabaseForCrossOwnerTest(User $user, string $name): UserDatabase
{
    $team = Team::firstOrCreate(['name' => 'Cross Owner Team'], ['description' => 't']);
    $group = GroupDatabase::create([
        'name' => "group-$name",
        'user_id' => $user->id,
        'team_id' => $team->id,
        'created_by' => $user->id,
    ]);

    $database = UserDatabase::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'database_name' => $name,
        'is_schema' => false,
        'created_by' => $user->id,
    ]);

    $group->members()->attach($database->id, [
        'group_id' => $group->id,
        'database_id' => $database->id,
    ]);

    return $database;
}

function makeCrossOwnerSuperAdmin(): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'Super Admin')->firstOrFail()->id);

    return $user;
}

function makeDeleteCapableNonOwner(): User
{
    $user = User::factory()->create();
    $role = Role::create(['name' => 'Cross Owner Deleter', 'description' => 'test']);
    $role->permissions()->attach(
        Permission::where('name', 'delete-databases')->firstOrFail()->id
    );
    $user->roles()->attach($role);

    return $user;
}

test('super admin can archive, restore and force delete databases owned by two different users', function () {
    $ownerA = User::factory()->create();
    $ownerB = User::factory()->create();
    $admin = makeCrossOwnerSuperAdmin();

    $dbA = makeDatabaseForCrossOwnerTest($ownerA, 'cross-owner-a-db');
    $dbB = makeDatabaseForCrossOwnerTest($ownerB, 'cross-owner-b-db');

    Http::preventStrayRequests();
    Http::fake(['*/v1/namespaces/*' => Http::response(['ok' => true], 200)]);

    // Archive both owners' databases.
    $this->actingAs($admin)
        ->delete('/databases/delete/cross-owner-a-db')
        ->assertSessionHasNoErrors();

    $this->actingAs($admin)
        ->delete('/databases/delete/cross-owner-b-db')
        ->assertSessionHasNoErrors();

    expect(UserDatabase::onlyTrashed()->where('database_name', $dbA->database_name)->exists())->toBeTrue()
        ->and(UserDatabase::onlyTrashed()->where('database_name', $dbB->database_name)->exists())->toBeTrue();

    // Restore owner A's database, leave owner B's archived.
    $this->actingAs($admin)
        ->post('/databases/restore', ['name' => $dbA->database_name])
        ->assertSessionHasNoErrors();

    expect(UserDatabase::where('database_name', $dbA->database_name)->exists())->toBeTrue()
        ->and(UserDatabase::onlyTrashed()->where('database_name', $dbB->database_name)->exists())->toBeTrue();

    // Permanently delete owner B's database.
    $this->actingAs($admin)
        ->delete('/databases/force-delete/' . $dbB->database_name)
        ->assertSessionHasNoErrors();

    expect(UserDatabase::withTrashed()->where('database_name', $dbA->database_name)->exists())->toBeTrue()
        ->and(UserDatabase::withTrashed()->where('database_name', $dbB->database_name)->exists())->toBeFalse();
});

test('a non-owner with delete permission still cannot archive another user database', function () {
    $owner = User::factory()->create();
    $intruder = makeDeleteCapableNonOwner();
    $database = makeDatabaseForCrossOwnerTest($owner, 'cross-owner-denied-db');

    Http::preventStrayRequests();

    $this->actingAs($intruder)
        ->delete('/databases/delete/' . $database->database_name)
        ->assertForbidden();

    expect(UserDatabase::where('database_name', $database->database_name)->exists())->toBeTrue();
});
