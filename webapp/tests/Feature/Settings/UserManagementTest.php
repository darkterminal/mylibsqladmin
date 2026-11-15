<?php

/**
 * User administration surface: create / update / deactivate / activate /
 * archive / restore / delete — with the permission gates pinned.
 */

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RoleUserSeeder', '--force' => true]);
});

function admin(): User
{
    $u = User::factory()->create();
    $u->roles()->attach(Role::where('name', 'Super Admin')->firstOrFail()->id);

    return $u;
}

function member(): User
{
    $u = User::factory()->create();
    $u->roles()->attach(Role::where('name', 'Member')->firstOrFail()->id);

    return $u;
}

test('super admin can create a user with a role', function () {
    $admin = admin();
    $memberRole = Role::where('name', 'Member')->firstOrFail();

    $this->actingAs($admin)->post('/users/create', [
        'name' => 'New User',
        'username' => 'newuser',
        'email' => 'new@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'roleSelection' => $memberRole->id,
    ])->assertRedirect();

    $user = User::where('username', 'newuser')->firstOrFail();
    expect($user->roles()->where('name', 'Member')->exists())->toBeTrue();
});

test('a member without create-users permission cannot create users', function () {
    $this->actingAs(member())->post('/users/create', [
        'name' => 'Nope',
        'username' => 'nopeuser',
        'email' => 'nope@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'roleSelection' => Role::where('name', 'Member')->firstOrFail()->id,
    ])->assertForbidden();

    expect(User::where('username', 'nopeuser')->exists())->toBeFalse();
});

test('deactivating a user blocks their access and revokes tokens', function () {
    $admin = admin();
    $user = member();
    $user->createToken('test-token');

    $this->actingAs($admin)->put("/users/{$user->id}/deactivate")->assertRedirect();

    $fresh = $user->fresh();
    expect($fresh->is_active)->toBeFalse()
        ->and($fresh->tokens()->count())->toBe(0);
});

test('re-activating a deactivated user restores their access', function () {
    $admin = admin();
    $user = member();
    $user->deactivate();

    $this->actingAs($admin)->put("/users/{$user->id}/activate")->assertRedirect();

    expect($user->fresh()->is_active)->toBeTrue();
});

test('a member cannot deactivate their own account', function () {
    $user = member();

    $this->actingAs($user)->put("/users/{$user->id}/deactivate")->assertForbidden();
});

test('super admin can update a user and change their role', function () {
    $admin = admin();
    $user = member();
    $teamManager = Role::where('name', 'Team Manager')->firstOrFail();

    $this->actingAs($admin)->put("/users/{$user->id}/update", [
        'name' => 'Promoted User',
        'username' => $user->username,
        'email' => $user->email,
        'roleSelections' => $teamManager->id,
        'teamSelections' => [],
        'teamPermissions' => [],
    ])->assertRedirect();

    $user->refresh();
    expect($user->name)->toBe('Promoted User')
        ->and($user->roles()->where('name', 'Team Manager')->exists())->toBeTrue();
});

test('super admin can soft-delete and restore a user', function () {
    $admin = admin();
    $user = member();

    $this->actingAs($admin)->delete("/users/{$user->id}/delete")->assertRedirect();
    expect(User::find($user->id))->toBeNull();
    expect(User::withTrashed()->find($user->id))->not->toBeNull();

    $this->actingAs($admin)->put("/users/{$user->id}/restore")->assertRedirect();
    expect(User::find($user->id))->not->toBeNull();
});
