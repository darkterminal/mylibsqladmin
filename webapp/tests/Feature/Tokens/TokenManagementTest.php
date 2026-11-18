<?php

/**
 * Token management HTTP surface — creation contract (name handling, hash
 * storage), list rendering (expiry state anchored at creation time), and
 * the search filter.
 */

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\UserDatabase;
use App\Models\UserDatabaseToken;
use Inertia\Testing\AssertableInertia as Assert;
use Illuminate\Support\Facades\Artisan;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RoleUserSeeder', '--force' => true]);
});

function tokenSuperAdmin(): User
{
    $u = User::factory()->create();
    $u->roles()->attach(Role::where('name', 'Super Admin')->firstOrFail()->id);

    return $u;
}

function teamWithDatabase(User $admin, string $dbName = 'tokens-test-db'): array
{
    $team = Team::create(['name' => 'Tokens Test Team', 'description' => 't']);
    $team->members()->attach($admin->id, ['permission_level' => 'super-admin']);

    $db = UserDatabase::create([
        'user_id' => $admin->id,
        'team_id' => $team->id,
        'database_name' => $dbName,
        'is_schema' => false,
        'created_by' => $admin->id,
    ]);

    $group = \App\Models\GroupDatabase::create([
        'name' => 'tokens-test-group',
        'user_id' => $admin->id,
        'team_id' => $team->id,
        'created_by' => $admin->id,
    ]);
    $group->members()->attach($db->id, ['group_id' => $group->id, 'database_id' => $db->id]);

    return [$team, $db];
}

test('a submitted token name is honoured and only the hash is stored', function () {
    $admin = tokenSuperAdmin();
    [$team, $db] = teamWithDatabase($admin);

    $this->actingAs($admin)
        ->withSession(['team_databases.team_id' => $team->id])
        ->post('/tokens/create', [
            'name' => 'ci-robot',
            'databaseId' => $db->id,
            'expiration' => 0,
            'userId' => $admin->id,
        ])
        ->assertRedirect();

    $stored = UserDatabaseToken::where('database_id', $db->id)->firstOrFail();

    expect($stored->name)->toBe('ci-robot')
        // 64 hex chars, lowercase — a sha256, never the plaintext
        ->and($stored->full_access_token)->toMatch('/^[0-9a-f]{64}$/')
        ->and($stored->read_only_token)->toMatch('/^[0-9a-f]{64}$/')
        ->and($stored->full_access_token)->not->toBe($stored->read_only_token);
});

test('a blank token name falls back to the per-user default', function () {
    $admin = tokenSuperAdmin();
    [$team, $db] = teamWithDatabase($admin, 'tokens-fallback-db');

    $this->actingAs($admin)
        ->withSession(['team_databases.team_id' => $team->id])
        ->post('/tokens/create', [
            'name' => '',
            'databaseId' => $db->id,
            'expiration' => 0,
            'userId' => $admin->id,
        ])
        ->assertRedirect();

    expect(UserDatabaseToken::where('database_id', $db->id)->firstOrFail()->name)
        ->toBe("Token for {$admin->name}");
});

test('expiration state is anchored at creation time, not render time', function () {
    $admin = tokenSuperAdmin();
    [$team, $db] = teamWithDatabase($admin, 'tokens-expiry-db');

    $token = UserDatabaseToken::create([
        'user_id' => $admin->id,
        'database_id' => $db->id,
        'name' => 'old-token',
        'full_access_token' => hash('sha256', 'full-secret'),
        'read_only_token' => hash('sha256', 'read-secret'),
        'expiration_day' => 30,
        'created_by' => $admin->id,
    ]);
    // 40 days old with a 30-day TTL — expired, deterministically.
    $token->created_at = now()->subDays(40);
    $token->updated_at = now()->subDays(40);
    $token->save();

    $this->actingAs($admin)
        ->withSession(['team_databases.team_id' => $team->id])
        ->get('/dashboard/tokens')
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard-token')
            ->where('userDatabaseTokens.data.0.expiration_state', 'expired')
            ->where('userDatabaseTokens.data.0.expires_at', now()->subDays(40)->addDays(30)->format('Y-m-d'))
            ->where('userDatabaseTokens.data.0.fingerprint', substr(hash('sha256', 'full-secret'), -8)));
});

test('the search filter narrows the list by token name', function () {
    $admin = tokenSuperAdmin();
    [$team, $dbA] = teamWithDatabase($admin, 'search-alpha-db');

    $dbB = UserDatabase::create([
        'user_id' => $admin->id,
        'team_id' => $team->id,
        'database_name' => 'search-beta-db',
        'is_schema' => false,
        'created_by' => $admin->id,
    ]);
    $team->groups()->firstOrFail(); // sanity: group exists from helper
    \App\Models\GroupDatabase::firstOrFail()->members()->attach($dbB->id, ['group_id' => \App\Models\GroupDatabase::firstOrFail()->id, 'database_id' => $dbB->id]);

    UserDatabaseToken::create([
        'user_id' => $admin->id,
        'database_id' => $dbA->id,
        'name' => 'alpha-token',
        'full_access_token' => hash('sha256', 'a'),
        'read_only_token' => hash('sha256', 'ra'),
        'expiration_day' => 0,
        'created_by' => $admin->id,
    ]);
    UserDatabaseToken::create([
        'user_id' => $admin->id,
        'database_id' => $dbB->id,
        'name' => 'beta-token',
        'full_access_token' => hash('sha256', 'b'),
        'read_only_token' => hash('sha256', 'rb'),
        'expiration_day' => 0,
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->withSession(['team_databases.team_id' => $team->id])
        ->get('/dashboard/tokens?search=alpha')
        ->assertInertia(fn (Assert $page) => $page
            ->component('dashboard-token')
            ->has('userDatabaseTokens.data', 1)
            ->where('userDatabaseTokens.data.0.name', 'alpha-token'));
});
