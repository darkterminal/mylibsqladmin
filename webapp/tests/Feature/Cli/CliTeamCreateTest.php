<?php

/**
 * Regression for F-50: POST /api/cli/team/create used to return 500 after
 * persisting the team, because belongsToMany::create() already inserts the
 * pivot row and the controller called attach() a second time.
 */

use App\Models\Role;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RoleUserSeeder', '--force' => true]);
});

function teamCliToken(): array
{
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::where('name', 'Super Admin')->firstOrFail()->id);

    $response = test()->postJson('/api/cli/login', [
        'username' => $admin->username,
        'password' => 'password',
    ]);

    return [$admin, $response->json('data.token')];
}

function teamCliHeaders(string $token): array
{
    return ['Authorization' => "Bearer {$token}", 'X-Request-Source' => 'CLI'];
}

test('cli team create returns 200 with a single owner pivot row', function () {
    [$admin, $token] = teamCliToken();

    $response = $this->withHeaders(teamCliHeaders($token))
        ->postJson('/api/cli/team/create', [
            'name' => 'CLI Smoke Team',
            'description' => 'dari uji coba CLI',
        ]);

    $response->assertOk()
        ->assertJsonPath('status', 'success')
        ->assertJsonPath('data.team.name', 'CLI Smoke Team');

    $teamId = $response->json('data.team.id');
    expect($teamId)->not->toBeNull();

    $pivots = DB::table('team_user')->where('team_id', $teamId)->get();
    expect($pivots)->toHaveCount(1)
        ->and($pivots->first()->user_id)->toBe($admin->id)
        ->and($pivots->first()->permission_level)->toBe('super-admin');
});
