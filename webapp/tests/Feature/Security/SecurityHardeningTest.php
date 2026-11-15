<?php

/**
 * Domain security tests — cover the failure modes that actually shipped:
 *  - v1.5.0-beta.1: fresh installs got zero users (unloginable)
 *  - v1.5.0-beta.2: token mint 500'd (bare $tokenGenerator)
 *  - SEC-20: deactivated users could still authenticate
 *  - FK violations on token mint must fail validation, not 500
 *
 * These exercise real HTTP handlers with RefreshDatabase; no mocks.
 */

use App\Models\Role;
use App\Models\User;
use App\Models\UserDatabase;
use App\Models\UserDatabaseToken;
use Illuminate\Support\Facades\Artisan;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('bootstrap seeder creates exactly one active super-admin and is idempotent', function () {
    Artisan::call('db:seed', ['--class' => 'BootstrapSeeder', '--force' => true]);
    Artisan::call('db:seed', ['--class' => 'BootstrapSeeder', '--force' => true]);

    expect(User::count())->toBe(1);

    $superAdmin = User::where('username', 'superadmin')->first();
    expect($superAdmin)->not->toBeNull()
        ->and($superAdmin->is_active)->toBeTrue()
        ->and($superAdmin->hasRole('Super Admin'))->toBeTrue();

    @unlink(storage_path('app/bootstrap-credentials.txt'));
});

test('deactivated users cannot authenticate with valid credentials', function () {
    $user = User::factory()->create(['is_active' => false]);

    $response = $this->post('/login', [
        'username' => $user->username,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response->assertSessionHasErrors('username');
});

test('a session of a user deactivated mid-session is flushed and redirected', function () {
    $user = User::factory()->create();

    $this->actingAs($user);
    $user->deactivate();

    $this->get('/dashboard')->assertRedirect('/login');
    $this->assertGuest();
});

test('authenticated admin can mint a token and it is stored hashed', function () {
    Artisan::call('db:seed', ['--class' => 'RoleUserSeeder', '--force' => true]);

    $admin = User::factory()->create();
    $admin->roles()->attach(Role::where('name', 'Super Admin')->firstOrFail()->id);

    $database = UserDatabase::create([
        'user_id' => $admin->id,
        'database_name' => 'domain-test-db',
        'is_schema' => '0',
        'created_by' => $admin->id,
    ]);

    $response = $this->actingAs($admin)->post('/tokens/create', [
        'name' => 'domain-test-token',
        'databaseId' => $database->id,
        'expiration' => 30,
        'userId' => $admin->id,
    ]);

    $response->assertRedirect();

    $stored = UserDatabaseToken::where('database_id', $database->id)->firstOrFail();
    expect($stored->full_access_token)->toMatch('/^[a-f0-9]{64}$/')
        ->and($stored->read_only_token)->toMatch('/^[a-f0-9]{64}$/');
});

test('token mint with a nonexistent database fails validation, not with 500', function () {
    Artisan::call('db:seed', ['--class' => 'RoleUserSeeder', '--force' => true]);

    $admin = User::factory()->create();
    $admin->roles()->attach(Role::where('name', 'Super Admin')->firstOrFail()->id);

    $response = $this->actingAs($admin)->from('/dashboard/tokens')->post('/tokens/create', [
        'name' => 'domain-test-token',
        'databaseId' => 999999,
        'expiration' => 30,
        'userId' => $admin->id,
    ]);

    $response->assertRedirect('/dashboard/tokens');
    $response->assertSessionHasErrors('databaseId');
    expect(UserDatabaseToken::count())->toBe(0);
});

test('guests cannot mint tokens', function () {
    $this->post('/tokens/create', [
        'name' => 'nope',
        'databaseId' => 1,
        'expiration' => 30,
        'userId' => 1,
    ])->assertRedirect('/login');
});
