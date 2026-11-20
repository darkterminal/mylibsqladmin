<?php

/**
 * Team management HTTP surface — create / delete / restore / invite /
 * revoke invitation / member role update / member removal / invitation
 * expiry.
 *
 * NOTE: assertions document the SEEDED RBAC matrix where relevant
 * (e.g. Team Manager may not create teams; Super Admin may).
 */

use App\Http\Controllers\TeamController;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RoleUserSeeder', '--force' => true]);
    Notification::fake();
});

function superAdminUser(): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'Super Admin')->firstOrFail()->id);

    return $user;
}

function teamMemberUser(): User
{
    $user = User::factory()->create();
    $user->roles()->attach(Role::where('name', 'Member')->firstOrFail()->id);

    return $user;
}

test('super admin can create a team and is attached as super-admin member', function () {
    $admin = superAdminUser();

    $this->actingAs($admin)
        ->post('/teams/create', ['name' => 'QA Team', 'description' => 'test'])
        ->assertRedirect();

    $team = Team::where('name', 'QA Team')->firstOrFail();
    expect($team->members()->where('user_id', $admin->id)->exists())->toBeTrue();
});

test('a member without create permission cannot create a team', function () {
    $member = teamMemberUser();

    $this->actingAs($member)
        ->post('/teams/create', ['name' => 'Nope', 'description' => 'test'])
        ->assertForbidden();

    expect(Team::where('name', 'Nope')->exists())->toBeFalse();
});

test('store member accepts the canonical database-maintainer role', function () {
    $admin = superAdminUser();
    $team = Team::create(['name' => 'Maint Team', 'description' => 'x']);
    $member = User::factory()->create();

    $this->actingAs($admin);

    $request = Request::create('/teams/members', 'POST', [
        'user_id' => $member->id,
        'permission_level' => 'database-maintainer',
    ]);

    app(TeamController::class)->storeMember($request, $team);

    $pivot = $team->members()->where('user_id', $member->id)->first()?->pivot;
    expect($pivot)->not->toBeNull();
    expect($pivot->permission_level)->toBe('database-maintainer');
});

test('store member rejects the misspelled database-maintener role', function () {
    $admin = superAdminUser();
    $team = Team::create(['name' => 'Typo Team', 'description' => 'x']);
    $member = User::factory()->create();

    $this->actingAs($admin);

    $request = Request::create('/teams/members', 'POST', [
        'user_id' => $member->id,
        'permission_level' => 'database-maintener',
    ]);

    expect(fn () => app(TeamController::class)->storeMember($request, $team))
        ->toThrow(ValidationException::class);

    expect($team->members()->where('user_id', $member->id)->exists())->toBeFalse();
});

test('super admin can soft-delete and restore a team', function () {
    $admin = superAdminUser();
    $team = Team::create(['name' => 'To Delete', 'description' => 'x']);
    $team->members()->attach($admin->id, ['permission_level' => 'super-admin']);

    $this->actingAs($admin)
        ->delete("/teams/delete/{$team->id}")
        ->assertOk();

    expect(Team::find($team->id))->toBeNull();
    expect(Team::withTrashed()->find($team->id))->not->toBeNull();

    $this->actingAs($admin)
        ->put("/teams/restore/{$team->id}")
        ->assertRedirect();

    expect(Team::find($team->id))->not->toBeNull();
});

test('invitation revoke marks the invitation as deleted', function () {
    $admin = superAdminUser();
    $team = Team::create(['name' => 'Invite Team', 'description' => 'x']);

    $invitation = Invitation::create([
        'name' => 'Invitee',
        'email' => 'invitee@example.com',
        'token' => Str::random(64),
        'team_id' => $team->id,
        'inviter_id' => $admin->id,
        'permission_level' => 'member',
        'expires_at' => now()->addDays(7),
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->delete("/teams/invitations/{$invitation->id}")
        ->assertOk()
        ->assertJsonPath('success', true);

    expect(Invitation::find($invitation->id))->toBeNull();
    expect(Invitation::withTrashed()->find($invitation->id))->not->toBeNull();
});

test('revoking a nonexistent invitation returns 404', function () {
    $admin = superAdminUser();

    $this->actingAs($admin)
        ->delete('/teams/invitations/999999')
        ->assertNotFound();
});

test('expired invitation acceptance redirects to the expired page', function () {
    $admin = superAdminUser();
    $team = Team::create(['name' => 'Exp Team', 'description' => 'x']);

    $invitation = Invitation::create([
        'name' => 'Late Invitee',
        'email' => 'late@example.com',
        'token' => Str::random(64),
        'team_id' => $team->id,
        'inviter_id' => $admin->id,
        'permission_level' => 'member',
        'expires_at' => now()->subDay(),
        'created_by' => $admin->id,
    ]);

    $response = $this->get(route('invitations.accept', $invitation->token));
    $response->assertRedirect(route('invitation.expired'));
});

test('a team manager can invite their team (member admin), super admin can, members cannot', function () {
    $manager = User::factory()->create();
    $manager->roles()->attach(Role::where('name', 'Team Manager')->firstOrFail()->id);

    $team = Team::create(['name' => 'Invite Team', 'description' => 'x']);
    $team->members()->attach($manager->id, ['permission_level' => 'team-manager']);

    $this->actingAs($manager)
        ->post("/teams/{$team->id}/invitations", [
            'name' => 'Invitee',
            'email' => 'invitee@example.com',
            'role' => 'member',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);

    $member = teamMemberUser();

    $this->actingAs($member)
        ->post("/teams/{$team->id}/invitations", [
            'name' => 'Invitee2',
            'email' => 'invitee2@example.com',
            'role' => 'member',
        ])
        ->assertForbidden();

    $admin = superAdminUser();

    $this->actingAs($admin)
        ->post("/teams/{$team->id}/invitations", [
            'name' => 'Invitee3',
            'email' => 'invitee3@example.com',
            'role' => 'member',
        ])
        ->assertOk()
        ->assertJsonPath('success', true);
});
