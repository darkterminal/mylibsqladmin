<?php

/**
 * CLI database endpoints against a faked sqld (Http::fake +
 * preventStrayRequests) — no live sqld required.
 */

use App\Models\GroupDatabase;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\UserDatabase;
use App\Models\UserDatabaseToken;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RoleUserSeeder', '--force' => true]);
    Http::preventStrayRequests();
    Http::fake(['*' => Http::response([], 200)]);
});

function cliToken(): array
{
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::where('name', 'Super Admin')->firstOrFail()->id);

    $response = test()->postJson('/api/cli/login', [
        'username' => $admin->username,
        'password' => 'password',
    ]);

    return [$admin, $response->json('data.token')];
}

function cliHeaders(string $token): array
{
    return ['Authorization' => "Bearer {$token}", 'X-Request-Source' => 'CLI'];
}

function seededDatabase(): Team
{
    $team = Team::create(['name' => 'CLI DB Team', 'description' => 't']);

    return $team;
}

test('cli can create a database via sqld (faked)', function () {
    [$admin, $token] = cliToken();

    $team = seededDatabase();
    $group = GroupDatabase::create([
        'name' => 'cli-group',
        'user_id' => $admin->id,
        'team_id' => $team->id,
        'created_by' => $admin->id,
    ]);

    $dbName = 'cli-db-' . uniqid();

    $this->withHeaders(cliHeaders($token))
        ->postJson('/api/cli/db/create', [
            'name' => $dbName,
            'is_schema' => '0',
            'group_id' => $group->id,
            'team_id' => $team->id,
        ])
        ->assertStatus(201)
        ->assertJsonPath('status', 'success');

    Http::assertSent(fn ($request) => str_contains($request->url(), "/v1/namespaces/{$dbName}/create"));

    expect(UserDatabase::where('database_name', $dbName)->exists())->toBeTrue();
});

test('cli can delete (archive) a database via sqld (faked)', function () {
    [$admin, $token] = cliToken();

    UserDatabase::create([
        'user_id' => $admin->id,
        'database_name' => 'cli-delete-db',
        'is_schema' => '0',
        'created_by' => $admin->id,
    ]);

    $this->withHeaders(cliHeaders($token))
        ->deleteJson('/api/cli/db/delete/cli-delete-db')
        ->assertOk()
        ->assertJsonPath('success', true);

    Http::assertSent(fn ($request) => str_contains($request->url(), '/v1/namespaces/cli-delete-db/fork/cli-delete-db-archived'));

    expect(UserDatabase::withTrashed()->where('database_name', 'cli-delete-db')->first()?->trashed())->toBeTrue();
});

test('cli can list archived databases', function () {
    [$admin, $token] = cliToken();

    $db = UserDatabase::create([
        'user_id' => $admin->id,
        'database_name' => 'cli-archived-db',
        'is_schema' => '0',
        'created_by' => $admin->id,
    ]);
    $db->delete();

    $this->withHeaders(cliHeaders($token))
        ->getJson('/api/cli/db/archives')
        ->assertOk()
        ->assertJsonPath('status', 'success');
});

test('cli can restore an archived database (faked)', function () {
    [$admin, $token] = cliToken();

    $db = UserDatabase::create([
        'user_id' => $admin->id,
        'database_name' => 'cli-restore-db',
        'is_schema' => '0',
        'created_by' => $admin->id,
    ]);
    $db->delete();

    $this->withHeaders(cliHeaders($token))
        ->postJson('/api/cli/db/restore/cli-restore-db')
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(UserDatabase::where('database_name', 'cli-restore-db')->exists())->toBeTrue();
});

test('cli mint endpoint issues a fresh token and stores only the hash', function () {
    [$admin, $token] = cliToken();

    UserDatabase::create([
        'user_id' => $admin->id,
        'database_name' => 'cli-mint-db',
        'is_schema' => '0',
        'created_by' => $admin->id,
    ]);

    $response = $this->withHeaders(cliHeaders($token))
        ->postJson('/api/cli/db/token/mint/cli-mint-db')
        ->assertStatus(201)
        ->assertJsonPath('status', 'success');

    $minted = $response->json('data.token');

    expect($minted)->toMatch('/^[a-f0-9]{64}$/');

    $stored = UserDatabaseToken::where('name', 'cli-shell-token')->firstOrFail();
    expect($stored->full_access_token)->toMatch('/^[a-f0-9]{64}$/')
        ->and($stored->full_access_token)->not->toBe($minted); // stored = sha256 of minted
});

test('cli token endpoint returns only a fingerprint', function () {
    [$admin, $token] = cliToken();

    UserDatabase::create([
        'user_id' => $admin->id,
        'database_name' => 'cli-print-db',
        'is_schema' => '0',
        'created_by' => $admin->id,
    ]);

    $this->withHeaders(cliHeaders($token))
        ->postJson('/api/cli/db/token/mint/cli-print-db')
        ->assertStatus(201);

    $response = $this->withHeaders(cliHeaders($token))
        ->getJson('/api/cli/db/token/cli-print-db')
        ->assertOk();

    $shown = $response->json('data.token');

    $stored = UserDatabaseToken::where('name', 'cli-shell-token')->firstOrFail();

    expect($shown)->toStartWith('••••••••')
        ->and($shown)->not->toBe($stored->full_access_token)
        ->and(mb_strlen($shown))->toBe(16); // 8 bullets + 8 hex chars
});
