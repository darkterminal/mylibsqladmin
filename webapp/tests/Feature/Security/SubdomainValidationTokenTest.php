<?php

/**
 * The CLI/proxy validator used to reject EVERY user token:
 * - it compared user_id against a hard-coded 0 (dead property, never
 *   assigned a real uid), and
 * - it compared the stored SHA-256 hash against the presented plaintext
 *   with ===, which can never match.
 *
 * These tests pin the fixed contract: a presented full-access token grants
 * full-access, a presented read-only token grants read-only, anything else
 * gets none.
 */

use App\Models\GroupDatabase;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\UserDatabase;
use App\Models\UserDatabaseToken;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RoleUserSeeder', '--force' => true]);
});

function makeUserTokenForDbTest(User $user, string $databaseName, string $fullToken, string $readOnlyToken): UserDatabase
{
    $team = Team::create(['name' => 'Validator Test Team', 'description' => 't']);
    $group = GroupDatabase::create([
        'name' => 'validator-test-group',
        'user_id' => $user->id,
        'team_id' => $team->id,
        'created_by' => $user->id,
    ]);

    $database = UserDatabase::create([
        'user_id' => $user->id,
        'team_id' => $team->id,
        'database_name' => $databaseName,
        'is_schema' => false,
        'created_by' => $user->id,
    ]);

    $group->members()->attach($database->id, [
        'group_id' => $group->id,
        'database_id' => $database->id,
    ]);

    UserDatabaseToken::create([
        'user_id' => $user->id,
        'database_id' => $database->id,
        'name' => "Token for {$user->name}",
        'full_access_token' => hash('sha256', $fullToken),
        'read_only_token' => hash('sha256', $readOnlyToken),
        'expiration_day' => 0,
        'created_by' => $user->id,
    ]);

    return $database;
}

function makeSuperAdminForValidatorTest(): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'Super Admin')->firstOrFail()->id);

    return $user;
}

test('a valid full-access token validates as full-access', function () {
    $admin = makeSuperAdminForValidatorTest();
    $full = bin2hex(random_bytes(32));
    $ro = bin2hex(random_bytes(32));
    makeUserTokenForDbTest($admin, 'validator-test-db', $full, $ro);

    $this->withHeaders([
        'X-Subdomain' => 'validator-test-db',
        'X-Auth-Token' => 'Bearer ' . $full,
    ])->get('/validate-subdomain')
        ->assertStatus(200)
        ->assertHeader('X-Access-Level', 'full-access');
});

test('a valid read-only token validates as read-only', function () {
    $admin = makeSuperAdminForValidatorTest();
    $full = bin2hex(random_bytes(32));
    $ro = bin2hex(random_bytes(32));
    makeUserTokenForDbTest($admin, 'validator-test-db', $full, $ro);

    $this->withHeaders([
        'X-Subdomain' => 'validator-test-db',
        'X-Auth-Token' => 'Bearer ' . $ro,
    ])->get('/validate-subdomain')
        ->assertStatus(200)
        ->assertHeader('X-Access-Level', 'read-only');
});

test('an unknown token validates as none', function () {
    $admin = makeSuperAdminForValidatorTest();
    makeUserTokenForDbTest($admin, 'validator-test-db', bin2hex(random_bytes(32)), bin2hex(random_bytes(32)));

    $this->withHeaders([
        'X-Subdomain' => 'validator-test-db',
        'X-Auth-Token' => 'Bearer not-a-real-token',
    ])->get('/validate-subdomain')
        ->assertStatus(200)
        ->assertHeader('X-Access-Level', 'none');
});

test('a valid token for a DIFFERENT database validates as none', function () {
    $admin = makeSuperAdminForValidatorTest();
    $full = bin2hex(random_bytes(32));
    $ro = bin2hex(random_bytes(32));
    makeUserTokenForDbTest($admin, 'validator-test-db', $full, $ro);

    $this->withHeaders([
        'X-Subdomain' => 'validator-other-db',
        'X-Auth-Token' => 'Bearer ' . $full,
    ])->get('/validate-subdomain')
        ->assertStatus(403)
        ->assertHeader('X-Access-Level', 'none');
});
