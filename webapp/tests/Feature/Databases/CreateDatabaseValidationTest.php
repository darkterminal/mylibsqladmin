<?php

/**
 * The create-database endpoint used to swallow ValidationException through
 * RespondsWithInternalError::jsonNotFoundOrError() and answer with a generic
 * 500 — no field errors, no log entry, no user-facing feedback. These tests
 * pin the 422 contract.
 */

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RoleUserSeeder', '--force' => true]);
});

function createDatabaseSuperAdmin(): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'Super Admin')->firstOrFail()->id);

    return $user;
}

test('create database without a group answers 422 with field errors', function () {
    $admin = createDatabaseSuperAdmin();

    $this->actingAs($admin)
        ->postJson('/databases/create', [
            'database' => 'audit-demo',
            'isSchema' => false,
            'teamId' => 1,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['groupId']);
});

test('create database without a team answers 422 with field errors', function () {
    $admin = createDatabaseSuperAdmin();

    $this->actingAs($admin)
        ->postJson('/databases/create', [
            'database' => 'audit-demo',
            'isSchema' => false,
            'groupId' => 1,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['teamId']);
});
