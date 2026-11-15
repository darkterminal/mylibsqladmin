<?php

/**
 * TECHDEBT-09 — the team_databases session payload must enforce its
 * expires_at instead of letting revoked access linger in the session.
 */

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Session;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

test('a fresh session payload is returned untouched', function () {
    $user = User::factory()->create();

    $payload = ['team_id' => null, 'databases' => [], 'groups' => [], 'expires_at' => now()->addHour()];

    session(['team_databases' => $payload]);

    $resolved = Team::currentTeamDatabases();

    expect($resolved['expires_at'])->not->toBeNull();
    expect(now()->diffInSeconds(\Illuminate\Support\Carbon::parse($resolved['expires_at']), false))
        ->toBeGreaterThan(0);
});

test('an expired session payload is transparently refreshed', function () {
    $user = User::factory()->create();
    $user->teams()->attach(
        \App\Models\Team::create(['name' => 'TTL Team', 'description' => 'test'])->id,
        ['permission_level' => 'super-admin']
    );

    session(['team_databases' => [
        'team_id' => null,
        'databases' => [],
        'groups' => [],
        'expires_at' => now()->subHour(),
    ]]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();

    $refreshed = session('team_databases');

    expect($refreshed['expires_at'])->not->toBeNull()
        ->and(\Illuminate\Support\Carbon::parse($refreshed['expires_at'])->isFuture())->toBeTrue();
});

test('a missing expires_at key is treated as expired', function () {
    $user = User::factory()->create();

    session(['team_databases' => [
        'team_id' => null,
        'databases' => [],
        'groups' => [],
    ]]);

    $this->actingAs($user)
        ->get('/dashboard')
        ->assertOk();

    $refreshed = session('team_databases');

    expect($refreshed['expires_at'])->not->toBeNull()
        ->and(\Illuminate\Support\Carbon::parse($refreshed['expires_at'])->isFuture())->toBeTrue();
});
