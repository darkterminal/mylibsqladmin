<?php

/**
 * Database lifecycle HTTP surface: create (sqld admin API faked), delete
 * (archive), restore, force-delete — with the SEC-06 authorization
 * semantics (owner or Super Admin + permissions).
 */

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\UserDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RoleUserSeeder', '--force' => true]);
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response([], 200)]);
});

function dbAdmin(): User
{
    $u = User::factory()->create();
    $u->roles()->attach(Role::where('name', 'Super Admin')->firstOrFail()->id);

    return $u;
}

test('super admin can create a database (sqld admin API is called)', function () {
    $admin = dbAdmin();
    $team = Team::create(['name' => 'Db Team', 'description' => 'd']);
    $team->members()->attach($admin->id, ['permission_level' => 'super-admin']);

    $group = \App\Models\GroupDatabase::create([
        'name' => 'db-group',
        'user_id' => $admin->id,
        'team_id' => $team->id,
        'created_by' => $admin->id,
    ]);

    $dbName = 'lifecycle-db-' . uniqid();

    $this->actingAs($admin)->post('/databases/create', [
        'database' => $dbName,
        'isSchema' => false,
        'groupId' => $group->id,
        'teamId' => $team->id,
    ])->assertOk()
        ->assertJsonPath('success', true);

    expect(UserDatabase::where('database_name', $dbName)->exists())->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), "/v1/namespaces/{$dbName}/create"));
});

test('a member without manage-databases cannot create a database', function () {
    $member = User::factory()->create();
    $member->roles()->attach(Role::where('name', 'Member')->firstOrFail()->id);

    $team = Team::create(['name' => 'Db Team 2', 'description' => 'd']);
    $team->members()->attach($member->id, ['permission_level' => 'member']);

    $group = \App\Models\GroupDatabase::create([
        'name' => 'db-group-2',
        'user_id' => $member->id,
        'team_id' => $team->id,
        'created_by' => $member->id,
    ]);

    $this->actingAs($member)->post('/databases/create', [
        'database' => 'lifecycle-nope',
        'isSchema' => false,
        'groupId' => $group->id,
        'teamId' => $team->id,
    ])->assertForbidden();
});

test('owner can delete (archive) own database; deletion goes through sqld fork', function () {
    $admin = dbAdmin();

    UserDatabase::create([
        'user_id' => $admin->id,
        'database_name' => 'lifecycle-delete-db',
        'is_schema' => '0',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)->delete('/databases/delete/lifecycle-delete-db')->assertRedirect();

    expect(UserDatabase::withTrashed()->where('database_name', 'lifecycle-delete-db')->first()?->trashed())->toBeTrue();
    Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/namespaces/lifecycle-delete-db/fork/lifecycle-delete-db-archived'));
});

test('a member cannot delete another user\'s database', function () {
    $admin = dbAdmin();
    $member = User::factory()->create();
    $member->roles()->attach(Role::where('name', 'Member')->firstOrFail()->id);

    UserDatabase::create([
        'user_id' => $admin->id,
        'database_name' => 'lifecycle-foreign-db',
        'is_schema' => '0',
        'created_by' => $admin->id,
    ]);

    $this->actingAs($member)->delete('/databases/delete/lifecycle-foreign-db')->assertForbidden();

    expect(UserDatabase::where('database_name', 'lifecycle-foreign-db')->exists())->toBeTrue();
});

test('unknown database name returns 404 on delete', function () {
    $admin = dbAdmin();

    $this->actingAs($admin)->delete('/databases/delete/does-not-exist')->assertNotFound();
});

test('restoring a soft-deleted database requires super admin', function () {
    $admin = dbAdmin();

    $db = UserDatabase::create([
        'user_id' => $admin->id,
        'database_name' => 'lifecycle-restore-db',
        'is_schema' => '0',
        'created_by' => $admin->id,
    ]);
    $db->delete(); // soft delete

    $this->actingAs($admin)->post('/databases/restore', ['name' => 'lifecycle-restore-db'])->assertRedirect();

    expect(UserDatabase::where('database_name', 'lifecycle-restore-db')->exists())->toBeTrue();
});
