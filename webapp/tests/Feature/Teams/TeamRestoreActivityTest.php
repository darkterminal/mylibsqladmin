<?php

/**
 * Restoring an archived team must log a team_restore activity — it used to
 * log TEAM_MEMBER_DELETE with "Team member deleted", which poisoned the
 * audit trail with the opposite meaning.
 */

use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RoleUserSeeder', '--force' => true]);
});

test('restoring a team logs a team_restore activity, not a member deletion', function () {
    $admin = User::factory()->create();
    $admin->roles()->attach(Role::where('name', 'Super Admin')->firstOrFail()->id);

    $team = Team::create(['name' => 'Restore Me', 'description' => null]);
    $team->delete(); // soft-delete -> archived

    $this->actingAs($admin)
        ->put(route('team.restore', $team->id))
        ->assertRedirect();

    expect($team->fresh()->deleted_at)->toBeNull();

    $log = Illuminate\Support\Facades\DB::table('user_activity_logs')
        ->where('type', 'team_restore')
        ->latest('id')
        ->first();

    expect($log)->not->toBeNull()
        ->and($log->description)->toContain('restored')
        ->and($log->description)->not->toContain('deleted');
});
