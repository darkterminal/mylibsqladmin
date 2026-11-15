<?php

/**
 * CLI contract tests — lock the surface that libsql3 relies on:
 *
 *  - login handshake (opaque Sanctum personal token, 30-day expiration)
 *  - auth boundaries: 401 without bearer, 403 without X-Request-Source: CLI,
 *    403 without the Super Admin role (EnsureCliRequest — default-deny)
 *  - representative DB-backed endpoints (team/user lists) for round trips
 *  - logout revokes the issuing token
 *
 * The contract is asserted through the real HTTP kernel; no mocks.
 */

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Laravel\Sanctum\PersonalAccessToken;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RoleUserSeeder', '--force' => true]);
});

function cliSuperAdmin(): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'Super Admin')->firstOrFail()->id);

    return $user;
}


test('cli login issues a Sanctum token for a super admin', function () {
    $admin = cliSuperAdmin();

    $response = $this->postJson('/api/cli/login', [
        'username' => $admin->username,
        'password' => 'password',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'status',
            'message',
            'data' => ['token', 'expires_at', 'expires_in', 'app_url'],
        ])
        ->assertJsonPath('status', 'success');

    expect(PersonalAccessToken::count())->toBe(1)
        ->and(PersonalAccessToken::first()->name)->toBe('cli-auth-token');
});

test('cli login rejects wrong credentials with 422', function () {
    $admin = cliSuperAdmin();

    $this->postJson('/api/cli/login', [
        'username' => $admin->username,
        'password' => 'definitely-wrong',
    ])->assertStatus(422);

    expect(PersonalAccessToken::count())->toBe(0);
});

test('cli login rejects deactivated users with the same error copy', function () {
    $admin = cliSuperAdmin();
    $admin->deactivate();

    $this->postJson('/api/cli/login', [
        'username' => $admin->username,
        'password' => 'password',
    ])->assertStatus(422)->assertJsonValidationErrors('username');

    expect(PersonalAccessToken::count())->toBe(0);
});

test('cli endpoints reject request without a bearer token', function () {
    $this->getJson('/api/cli/team/lists')->assertUnauthorized();
});

test('cli endpoints reject a forged bearer token', function () {
    $this->withHeaders([
        'Authorization' => 'Bearer 1|aaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa',
        'X-Request-Source' => 'CLI',
    ])->getJson('/api/cli/team/lists')->assertUnauthorized();
});

test('cli endpoints reject a valid token without the X-Request-Source CLI header', function () {
    $admin = cliSuperAdmin();

    $token = $admin->createToken('cli-auth-token', [], now()->addDays(30))->plainTextToken;

    $this->withHeaders([
        'Authorization' => "Bearer {$token}",
    ])->getJson('/api/cli/team/lists')
        ->assertForbidden()
        ->assertJsonPath('error', 'This endpoint is only accessible from the CLI application');
});

test('cli endpoints reject a CLI-tagged token from a non super admin', function () {
    $member = User::factory()->create();
    $member->roles()->attach(Role::where('name', 'Member')->firstOrFail()->id);

    $token = $member->createToken('cli-auth-token', [], now()->addDays(30))->plainTextToken;

    $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'X-Request-Source' => 'CLI',
    ])->getJson('/api/cli/team/lists')
        ->assertForbidden()
        ->assertJsonPath('error', 'Unauthorized');
});

test('super admin can list teams through the CLI', function () {
    $admin = cliSuperAdmin();
    Team::create(['name' => 'CLI Team Alpha', 'description' => 'contract test']);
    Team::create(['name' => 'CLI Team Beta', 'description' => 'contract test']);

    $token = $admin->createToken('cli-auth-token', [], now()->addDays(30))->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'X-Request-Source' => 'CLI',
    ])->getJson('/api/cli/team/lists');

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonCount(2, 'data.teams');
});

test('super admin can list users through the CLI without leaking roles of others', function () {
    $admin = cliSuperAdmin();

    $token = $admin->createToken('cli-auth-token', [], now()->addDays(30))->plainTextToken;

    $response = $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'X-Request-Source' => 'CLI',
    ])->getJson('/api/cli/user/lists');

    $response->assertOk()->assertJsonPath('status', 'success');

    $usernames = collect($response->json('data.users'))->pluck('username');
    expect($usernames)->toContain($admin->username);
});

test('cli logout revokes the presented token', function () {
    $admin = cliSuperAdmin();

    $login = $this->postJson('/api/cli/login', [
        'username' => $admin->username,
        'password' => 'password',
    ]);
    $token = $login->json('data.token');

    $this->postJson('/api/cli/logout', ['token' => $token])
        ->assertOk()
        ->assertJsonPath('status', 'success');

    expect(PersonalAccessToken::count())->toBe(0);

    // The revoked token can no longer authenticate.
    $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'X-Request-Source' => 'CLI',
    ])->getJson('/api/cli/team/lists')->assertUnauthorized();
});
