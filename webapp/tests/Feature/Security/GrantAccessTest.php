<?php

/**
 * Grant-access domain tests — this endpoint was completely broken (500 for
 * everyone) since the SEC-06 class-string middleware met an instance-typed
 * policy. These tests pin the intended behaviour:
 *
 *  - super admin can grant any database to any user
 *  - users with manage-databases may only grant databases they own
 *  - users without manage-databases may not grant at all
 *  - guests are redirected to login
 */

use App\Models\GrantedDatabase;
use App\Models\Role;
use App\Models\User;
use App\Models\UserDatabase;
use Illuminate\Support\Facades\Artisan;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RoleUserSeeder', '--force' => true]);
});

function adminUser(): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'Super Admin')->firstOrFail()->id);

    return $user;
}

function dbOwnedBy(User $owner): UserDatabase
{
    return UserDatabase::create([
        'user_id' => $owner->id,
        'database_name' => 'db-' . uniqid(),
        'is_schema' => '0',
        'created_by' => $owner->id,
    ]);
}

test('super admin can grant any database to any user', function () {
    $admin = adminUser();
    $owner = User::factory()->create();
    $grantee = User::factory()->create();

    $database = dbOwnedBy($owner);

    $this->actingAs($admin)->post('/databases/grant-access', [
        'userId' => $grantee->id,
        'databaseId' => $database->id,
    ])->assertRedirect();

    expect(GrantedDatabase::where('user_id', $grantee->id)
        ->where('database_id', $database->id)
        ->exists())->toBeTrue();
});

test('member without manage-databases cannot grant, even their own database', function () {
    $member = User::factory()->create();
    $member->roles()->attach(Role::where('name', 'Member')->firstOrFail()->id);

    $grantee = User::factory()->create();
    $database = dbOwnedBy($member);

    $this->actingAs($member)->post('/databases/grant-access', [
        'userId' => $grantee->id,
        'databaseId' => $database->id,
    ])->assertForbidden();

    expect(GrantedDatabase::count())->toBe(0);
});

test('team manager without delete-databases cannot grant', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach(Role::where('name', 'Team Manager')->firstOrFail()->id);

    $grantee = User::factory()->create();
    $database = dbOwnedBy($manager);

    $this->actingAs($manager)->post('/databases/grant-access', [
        'userId' => $grantee->id,
        'databaseId' => $database->id,
    ])->assertForbidden();
});

test('granting a nonexistent database fails validation', function () {
    $admin = adminUser();
    $grantee = User::factory()->create();

    $this->actingAs($admin)->from('/dashboard/databases')->post('/databases/grant-access', [
        'userId' => $grantee->id,
        'databaseId' => 999999,
    ])->assertRedirect('/dashboard/databases')
        ->assertSessionHasErrors('databaseId');
});

test('guests cannot grant access', function () {
    $owner = User::factory()->create();
    $grantee = User::factory()->create();
    $database = dbOwnedBy($owner);

    $this->post('/databases/grant-access', [
        'userId' => $grantee->id,
        'databaseId' => $database->id,
    ])->assertRedirect('/login');
});
