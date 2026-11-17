<?php

/**
 * archiveDatabase() used to soft-delete the local record before the sqld
 * fork. When the fork failed, the database vanished from the UI while the
 * sqld namespace was still alive. The service now forks first and only
 * deletes the local record afterwards.
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

function makeDatabaseForArchiveTest(User $user, string $name): UserDatabase
{
    $team = Team::firstOrCreate(['name' => 'Archive Order Team'], ['description' => 't']);
    $group = GroupDatabase::create([
        'name' => 'archive-order-group',
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

function makeArchiveTestAdmin(): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'Super Admin')->firstOrFail()->id);

    return $user;
}

test('archive keeps the local record when the sqld fork fails', function () {
    $admin = makeArchiveTestAdmin();
    $database = makeDatabaseForArchiveTest($admin, 'archive-fork-fail-db');

    Http::preventStrayRequests();
    Http::fake([
        '*/v1/namespaces/*/fork/*' => Http::response(['error' => 'namespace not found'], 500),
    ]);

    $this->actingAs($admin)
        ->delete('/databases/delete/archive-fork-fail-db')
        ->assertSessionHas('error', 'Database deletion failed');

    expect(UserDatabase::where('database_name', $database->database_name)->exists())->toBeTrue()
        ->and(UserDatabase::onlyTrashed()->where('database_name', $database->database_name)->exists())->toBeFalse();
});

test('archive succeeds when the sqld fork and delete succeed', function () {
    $admin = makeArchiveTestAdmin();
    $database = makeDatabaseForArchiveTest($admin, 'archive-fork-ok-db');

    Http::preventStrayRequests();
    Http::fake([
        '*/v1/namespaces/*' => Http::response(['ok' => true], 200),
    ]);

    $this->actingAs($admin)
        ->delete('/databases/delete/archive-fork-ok-db')
        ->assertSessionHasNoErrors();

    expect(UserDatabase::where('database_name', $database->database_name)->exists())->toBeFalse()
        ->and(UserDatabase::onlyTrashed()->where('database_name', $database->database_name)->exists())->toBeTrue();
});

test('restore keeps the local record archived when the sqld fork fails', function () {
    $admin = makeArchiveTestAdmin();
    $database = makeDatabaseForArchiveTest($admin, 'restore-fork-fail-db');

    // Simulate the archived state directly (fork path is covered by its own test)
    $database->delete();

    Http::preventStrayRequests();
    Http::fake([
        '*/v1/namespaces/*/fork/restore-fork-fail-db' => Http::response(['error' => 'fork failed'], 500),
    ]);

    $this->actingAs($admin)
        ->post('/databases/restore', ['name' => 'restore-fork-fail-db'])
        ->assertSessionHas('error', 'Database restore failed');

    expect(UserDatabase::where('database_name', $database->database_name)->exists())->toBeFalse()
        ->and(UserDatabase::onlyTrashed()->where('database_name', $database->database_name)->exists())->toBeTrue();
});

test('restore succeeds when the sqld fork and delete succeed', function () {
    $admin = makeArchiveTestAdmin();
    $database = makeDatabaseForArchiveTest($admin, 'restore-fork-ok-db');

    // Simulate the archived state directly (fork path is covered by its own test)
    $database->delete();

    Http::preventStrayRequests();
    Http::fake([
        '*/v1/namespaces/*' => Http::response(['ok' => true], 200),
    ]);

    $this->actingAs($admin)
        ->post('/databases/restore', ['name' => 'restore-fork-ok-db'])
        ->assertSessionHasNoErrors();

    expect(UserDatabase::where('database_name', $database->database_name)->exists())->toBeTrue();
});

test('force delete keeps the local record when the sqld delete fails', function () {
    $admin = makeArchiveTestAdmin();
    $database = makeDatabaseForArchiveTest($admin, 'force-delete-fail-db');
    $database->delete();

    Http::preventStrayRequests();
    Http::fake([
        '*/v1/namespaces/*' => Http::response(['error' => 'namespace missing'], 500),
    ]);

    $this->actingAs($admin)
        ->delete('/databases/force-delete/force-delete-fail-db')
        ->assertSessionHas('error', 'Database force deletion failed');

    expect(UserDatabase::onlyTrashed()->where('database_name', $database->database_name)->exists())->toBeTrue();
});

test('force delete removes the local record when the sqld delete succeeds', function () {
    $admin = makeArchiveTestAdmin();
    $database = makeDatabaseForArchiveTest($admin, 'force-delete-ok-db');
    $database->delete();

    Http::preventStrayRequests();
    Http::fake([
        '*/v1/namespaces/*' => Http::response(['ok' => true], 200),
    ]);

    $this->actingAs($admin)
        ->delete('/databases/force-delete/force-delete-ok-db')
        ->assertSessionHasNoErrors();

    expect(UserDatabase::withTrashed()->where('database_name', $database->database_name)->exists())->toBeFalse();
});
