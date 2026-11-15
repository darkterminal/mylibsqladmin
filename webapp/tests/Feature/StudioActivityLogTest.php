<?php

/**
 * Activity logging for the Database Studio. The endpoint must not leak raw
 * SQL into logs, must not crash on unrecognised statements, and must not
 * compute the action twice per statement.
 */

use App\Models\ActivityLog;
use App\Models\Team;
use App\Models\User;
use App\Models\UserDatabase;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

function studioTeamAndDatabase(User $owner): array
{
    $team = Team::create(['name' => 'Studio Team', 'description' => 'studio']);
    $team->members()->attach($owner->id, ['permission_level' => 'super-admin']);

    $database = UserDatabase::create([
        'user_id' => $owner->id,
        'team_id' => $team->id,
        'database_name' => 'studio-db-' . uniqid(),
        'is_schema' => '0',
        'created_by' => $owner->id,
    ]);

    return [$team, $database];
}

test('a recognised statement logs CREATE TABLE with the table name', function () {
    $user = User::factory()->create();
    [$team, $database] = studioTeamAndDatabase($user);

    $this->actingAs($user)->postJson('/activities', [
        'team_id' => $team->id,
        'database_name' => $database->database_name,
        'query' => 'CREATE TABLE widgets (id INTEGER PRIMARY KEY)',
    ])->assertStatus(201);

    expect(ActivityLog::count())->toBe(1);
    expect(ActivityLog::first()->action)->toContain('Created table [widgets]');

    expect(ActivityLog::count())->toBe(1);
});

test('an unrecognised statement gets a safe fallback action, not a 500', function () {
    $user = User::factory()->create();
    [$team, $database] = studioTeamAndDatabase($user);

    $this->actingAs($user)->postJson('/activities', [
        'team_id' => $team->id,
        'database_name' => $database->database_name,
        'query' => 'PRAGMA journal_mode=wal',
    ])->assertStatus(201)->assertJsonPath('activity.action', 'Executed query');

    expect(ActivityLog::count())->toBe(1);
});

test('guests cannot log studio activity', function () {
    $this->postJson('/activities', [
        'team_id' => 1,
        'database_name' => 'whatever',
        'query' => 'SELECT 1',
    ])->assertUnauthorized();
});
