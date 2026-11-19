<?php

/**
 * Opening the Database Studio must never ask the user for a token.
 * The page mints one automatically for every allowed database — a fresh
 * full-access token per visit, scoped to the user and the database.
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

function makeStudioTargetDatabase(User $user, string $name): UserDatabase
{
    $team = Team::firstOrCreate(['name' => 'Studio Auto-Token Team'], ['description' => 't']);
    $group = GroupDatabase::create([
        'name' => 'studio-auto-token-group',
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

function makeRoleUserForStudioTest(string $role): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', $role)->firstOrFail()->id);

    return $user;
}

test('an authorized user receives a fresh full-access studio token', function () {
    $admin = makeRoleUserForStudioTest('Super Admin');
    $database = makeStudioTargetDatabase($admin, 'studio-auto-token-db');

    $response = $this->actingAs($admin)
        ->postJson('/database-studio/token', ['database' => 'studio-auto-token-db'])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['full_access_token']);

    $token = $response->json('full_access_token');
    expect($token)->not->toBeEmpty();

    // The CLI/proxy validator accepts this token as full-access for that database
    $this->withHeaders([
        'X-Subdomain' => 'studio-auto-token-db',
        'X-Auth-Token' => 'Bearer ' . $token,
    ])->get('/validate-subdomain')
        ->assertStatus(200)
        ->assertHeader('X-Access-Level', 'full-access');

    $stored = UserDatabaseToken::where('user_id', $admin->id)
        ->where('database_id', $database->id)
        ->firstOrFail();
    expect($stored->name)->toBe('Studio — studio-auto-token-db')
        ->and($stored->full_access_token)->toBe(hash('sha256', $token));
});

test('a Team Manager can mint a studio token for a database they have no grant for', function () {
    // A Team Manager's role alone authorizes token minting for any
    // database — explicit GrantedDatabase is only required for Members.
    $manager = makeRoleUserForStudioTest('Team Manager');
    $owner = makeRoleUserForStudioTest('Super Admin');
    $database = makeStudioTargetDatabase($owner, 'studio-team-manager-mint-db');

    $response = $this->actingAs($manager)
        ->postJson('/database-studio/token', ['database' => 'studio-team-manager-mint-db'])
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonStructure(['full_access_token']);

    $stored = UserDatabaseToken::where('user_id', $manager->id)
        ->where('database_id', $database->id)
        ->firstOrFail();
    expect($stored->full_access_token)->not->toBeEmpty();
});

test('a user without access to the database is refused with 403', function () {
    $admin = makeRoleUserForStudioTest('Super Admin');
    $outsider = makeRoleUserForStudioTest('Member');
    makeStudioTargetDatabase($admin, 'studio-auto-forbidden-db');

    $this->actingAs($outsider)
        ->postJson('/database-studio/token', ['database' => 'studio-auto-forbidden-db'])
        ->assertStatus(403);

    expect(UserDatabaseToken::count())->toBe(0);
});
