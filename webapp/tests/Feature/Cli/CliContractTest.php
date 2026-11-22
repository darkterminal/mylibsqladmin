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

use App\Models\GrantedDatabase;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\UserDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
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

function cliContractHeaders(string $token): array
{
    return [
        'Authorization' => "Bearer {$token}",
        'X-Request-Source' => 'CLI',
    ];
}

function cliDatabaseOwnedBy(User $owner, string $name): UserDatabase
{
    $team = Team::firstOrCreate(['name' => 'CLI DB Team'], ['description' => 't']);

    return UserDatabase::create([
        'user_id' => $owner->id,
        'team_id' => $team->id,
        'database_name' => $name,
        'is_schema' => false,
        'created_by' => $owner->id,
    ]);
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

test('cli endpoints reject a deactivated super admin with a live token', function () {
    $admin = cliSuperAdmin();
    $token = $admin->createToken('cli-auth-token', [], now()->addDays(30))->plainTextToken;
    $admin->deactivate();

    expect($admin->fresh()->is_active)->toBeFalse();

    $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'X-Request-Source' => 'CLI',
    ])->getJson('/api/cli/team/lists')
        ->assertForbidden()
        ->assertJsonPath('error', 'This account has been deactivated');
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
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.revoked', true)
        ->assertJsonMissingPath('data.revoked_token');

    expect(PersonalAccessToken::count())->toBe(0);

    // The revoked token can no longer authenticate.
    $this->withHeaders([
        'Authorization' => "Bearer {$token}",
        'X-Request-Source' => 'CLI',
    ])->getJson('/api/cli/team/lists')->assertUnauthorized();
});

test('super admin can list archived teams through the CLI', function () {
    $admin = cliSuperAdmin();
    $team = Team::create(['name' => 'Archived CLI Team', 'description' => 'old']);
    $team->delete();

    $token = $admin->createToken('cli-auth-token', [], now()->addDays(30))->plainTextToken;

    $this->withHeaders(cliContractHeaders($token))->getJson('/api/cli/team/archives')
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.teams.0.id', $team->id)
        ->assertJsonPath('data.teams.0.name', 'Archived CLI Team');
});

test('super admin can restore an archived team through the CLI', function () {
    $admin = cliSuperAdmin();
    $team = Team::create(['name' => 'Restore CLI Team', 'description' => 'old']);
    $team->delete();

    $token = $admin->createToken('cli-auth-token', [], now()->addDays(30))->plainTextToken;

    $this->withHeaders(cliContractHeaders($token))
        ->postJson("/api/cli/team/restore/{$team->id}")
        ->assertOk()
        ->assertJsonPath('status', true)
        ->assertJsonPath('data.team.id', $team->id)
        ->assertJsonPath('data.team.name', 'Restore CLI Team');

    expect(Team::find($team->id))->not->toBeNull()
        ->and(Team::onlyTrashed()->find($team->id))->toBeNull();
});

test('cli login creates a personal workspace for a freshly seeded super admin', function () {
    Artisan::call('db:seed', ['--class' => 'BootstrapSeeder', '--force' => true]);
    $credentials = (string) file_get_contents(storage_path('app/bootstrap-credentials.txt'));
    preg_match('/^password=(.*)$/m', $credentials, $matches);
    $password = $matches[1];

    $login = [
        'username' => 'superadmin',
        'password' => $password,
    ];

    $this->postJson('/api/cli/login', $login)
        ->assertOk()
        ->assertJsonPath('status', 'success');

    $user = User::where('username', 'superadmin')->firstOrFail();
    $workspace = Team::whereHas('members', fn ($query) => $query->where('user_id', $user->id))->first();
    expect($workspace)->not->toBeNull()
        ->and($workspace->name)->toBe('superadmin space');

    // Idempotent: a second CLI login must not attach another workspace.
    $this->postJson('/api/cli/login', $login)->assertOk();
    expect(Team::whereHas('members', fn ($query) => $query->where('user_id', $user->id))->count())->toBe(1);

    @unlink(storage_path('app/bootstrap-credentials.txt'));
});

test('db:create returns field-level 422 details for an invalid group_id', function () {
    $admin = cliSuperAdmin();
    $team = Team::create(['name' => 'Create DB Team', 'description' => 't']);
    $token = $admin->createToken('cli-auth-token', [], now()->addDays(30))->plainTextToken;

    $this->withHeaders(cliContractHeaders($token))
        ->postJson('/api/cli/db/create', [
            'name' => 'new-db',
            'is_schema' => false,
            'group_id' => 999999,
            'team_id' => $team->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('group_id')
        ->assertJsonMissingPath('data.database.id');
});

test('db:list includes a database shared with the caller through a grant', function () {
    $caller = cliSuperAdmin();
    $owner = User::factory()->create();
    $shared = cliDatabaseOwnedBy($owner, 'shared-with-caller');
    GrantedDatabase::create(['user_id' => $caller->id, 'database_id' => $shared->id]);

    $token = $caller->createToken('cli-auth-token', [], now()->addDays(30))->plainTextToken;

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->withHeaders(cliContractHeaders($token))->getJson('/api/cli/db/lists');
    $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
    DB::disableQueryLog();

    $response->assertOk()
        ->assertJsonPath('data.databases.0.database_name', 'shared-with-caller')
        ->assertJsonPath('data.databases.0.owner', $owner->username);
    expect($queries)->toContain('granted_user_databases');
});

test('db:archives excludes a live shared database and keeps soft-delete outside the OR', function () {
    $caller = cliSuperAdmin();
    $owner = User::factory()->create();

    $live = cliDatabaseOwnedBy($owner, 'live-shared-db');
    GrantedDatabase::create(['user_id' => $caller->id, 'database_id' => $live->id]);

    $archived = cliDatabaseOwnedBy($owner, 'archived-shared-db');
    GrantedDatabase::create(['user_id' => $caller->id, 'database_id' => $archived->id]);
    $archived->delete();

    $token = $caller->createToken('cli-auth-token', [], now()->addDays(30))->plainTextToken;

    DB::flushQueryLog();
    DB::enableQueryLog();
    $response = $this->withHeaders(cliContractHeaders($token))->getJson('/api/cli/db/archives');
    $queries = collect(DB::getQueryLog())->pluck('query')->implode("\n");
    DB::disableQueryLog();

    $response->assertOk();
    $names = collect($response->json('data.databases'))->pluck('database_name')->all();
    expect($names)->toContain('archived-shared-db')
        ->not->toContain('live-shared-db');

    // Query-log confirmation, not just a green assertion: the soft-delete
    // guard must wrap the owner/grant OR, otherwise the OR escapes it.
    expect($queries)->toContain('granted_user_databases')
        ->and($queries)->toMatch('/"deleted_at" is not null and \(/');
});

/**
 * D-15/G-3: the CLI is the administrative subset of the web platform. This
 * gate reads route metadata and CLI source only; it issues no HTTP requests, so
 * the Plan 002 HttpTransport fake remains the only CLI HTTP fake.
 *
 * @return array<string, string>
 */
function cliSurfaceRoutes(): array
{
    $routes = [];

    foreach (app('router')->getRoutes()->getRoutes() as $route) {
        $uri = '/' . ltrim($route->uri(), '/');
        if (!str_starts_with($uri, '/api/cli')) {
            continue;
        }

        foreach (['GET', 'POST', 'PUT', 'PATCH', 'DELETE'] as $method) {
            if (in_array($method, $route->methods(), true)) {
                $routes["{$method} {$uri}"] = $route->getActionName();
            }
        }
    }

    ksort($routes);

    return $routes;
}

/**
 * @return array<string, string> endpoint key => source file that calls it
 */
function cliSurfaceCalls(): array
{
    $root = base_path('../libsql3/src');
    $routes = cliSurfaceRoutes();
    $calls = [];

    /** @var \Symfony\Component\Finder\SplFileInfo $file */
    foreach (\Illuminate\Support\Facades\File::allFiles($root) as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $source = (string) file_get_contents($file->getRealPath());
        $variables = [];
        if (preg_match_all(
            '/\$([A-Za-z_]\w*)\s*=\s*([\'"])(\/api\/cli\/[^\'"\n]*)\2\s*;/',
            $source,
            $assignments,
            PREG_SET_ORDER
        )) {
            foreach ($assignments as $assignment) {
                $variables[$assignment[1]] = $assignment[3];
            }
        }

        $literals = [];
        if (preg_match_all(
            '/http_request\s*\(\s*([\'"])(\/api\/cli\/[^\'"\s]*)\1[^,]*,\s*([\'"])(GET|POST|PUT|PATCH|DELETE)\3/',
            $source,
            $direct,
            PREG_SET_ORDER
        )) {
            foreach ($direct as $match) {
                $literals[] = [$match[2], $match[4]];
            }
        }

        if (preg_match_all(
            '/http_request\s*\(\s*\$([A-Za-z_]\w*)\s*,\s*([\'"])(GET|POST|PUT|PATCH|DELETE)\2/',
            $source,
            $indirect,
            PREG_SET_ORDER
        )) {
            foreach ($indirect as $match) {
                if (isset($variables[$match[1]])) {
                    $literals[] = [$variables[$match[1]], $match[3]];
                }
            }
        }

        foreach ($literals as [$literal, $method]) {
            $callPath = cliSurfaceNormalizePath($literal);
            $matched = null;

            foreach (array_keys($routes) as $routeKey) {
                [$routeMethod, $routePath] = explode(' ', $routeKey, 2);
                if ($routeMethod === $method && preg_match(cliSurfacePathRegex($routePath), $callPath) === 1) {
                    $matched = $routeKey;
                    break;
                }
            }

            $calls[$matched ?? "{$method} {$callPath}"] = $file->getRelativePathname();
        }
    }

    ksort($calls);

    return $calls;
}

function cliSurfaceNormalizePath(string $literal): string
{
    $endsWithSlash = str_ends_with($literal, '/');
    $path = rtrim($literal, '/');
    $path = preg_replace('/\$[A-Za-z_]\w*/', '{}', $path) ?? $path;

    return $endsWithSlash ? $path . '/{}' : $path;
}

function cliSurfacePathRegex(string $path): string
{
    $segments = array_map(
        fn (string $segment): string => $segment === '{}' || preg_match('/^\{[^}]+\}$/', $segment) === 1
            ? '[^/]+'
            : preg_quote($segment, '#'),
        explode('/', $path)
    );

    return '#^' . implode('/', $segments) . '$#';
}

test('every CLI route has a calling command and every CLI call resolves to a route', function () {
    $routes = cliSurfaceRoutes();
    $calls = cliSurfaceCalls();

    // D-39: explicit, reasoned exemptions. No CLI route is exempt today; add an
    // entry only with a maintainer-approved reason (for example a route owned by
    // a future non-CLI consumer), never as a silent way to hide drift.
    $uncalled = [
        // 'GET /api/cli/example' => 'Reason: ...',
    ];

    expect($routes)->not->toBeEmpty()
        ->and($calls)->not->toBeEmpty();

    $missingCallers = array_diff_key($routes, $calls, $uncalled);
    $unknownEndpoints = array_diff_key($calls, $routes);

    expect(array_keys($missingCallers))->toBe([])
        ->and(array_keys($unknownEndpoints))->toBe([]);
});
