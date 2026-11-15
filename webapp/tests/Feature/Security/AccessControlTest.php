<?php

/**
 * Access-control domain tests — batch 2 of TEST-02.
 *
 * Covers the authorization boundaries that were explicitly hardened during the
 * v1.5.0 security remediation: invitation email binding (SEC-09), tenant
 * scoping of team policies (SEC-06), and token-delete ownership (SEC-04).
 */

use App\Models\Invitation;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Models\UserDatabase;
use App\Models\UserDatabaseToken;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => 'RoleUserSeeder', '--force' => true]);
});

function makeTeam(User $creator, string $name): Team
{
    $team = Team::create(['name' => $name, 'description' => 'test team']);
    $team->members()->attach($creator->id, ['permission_level' => 'super-admin']);

    return $team;
}

function makeInvitation(Team $team, User $inviter, string $email, string $role = 'Member'): Invitation
{
    return Invitation::create([
        'name' => 'Invitee',
        'email' => $email,
        'token' => Str::random(64),
        'team_id' => $team->id,
        'inviter_id' => $inviter->id,
        'permission_level' => strtolower($role),
        'expires_at' => now()->addDays(7),
        'created_by' => $inviter->id,
    ]);
}

test('authenticated user with the invited email may accept', function () {
    $inviter = User::factory()->create();
    $invitee = User::factory()->create();
    $team = makeTeam($inviter, 'Invite Team');
    $invitation = makeInvitation($team, $inviter, $invitee->email);

    $response = $this->actingAs($invitee)->get(route('invitations.accept', $invitation->token));

    $response->assertRedirect(route('dashboard.teams'));
    expect($invitee->teams()->where('teams.id', $team->id)->exists())->toBeTrue();
    expect(Invitation::find($invitation->id))->toBeNull();
});

test('authenticated user with a different email may not accept', function () {
    $inviter = User::factory()->create();
    $invitee = User::factory()->create();
    $other = User::factory()->create();
    $team = makeTeam($inviter, 'Invite Team');
    $invitation = makeInvitation($team, $inviter, $invitee->email);

    $response = $this->actingAs($other)->get(route('invitations.accept', $invitation->token));

    $response->assertSessionHas('error');
    expect($other->teams()->where('teams.id', $team->id)->exists())->toBeFalse();
    expect(Invitation::where('id', $invitation->id)->exists())->toBeTrue();
});

test('guest with a pre-existing account joining via invitation gets attached at the invited email', function () {
    $inviter = User::factory()->create();
    $existing = User::factory()->create();
    $team = makeTeam($inviter, 'Guest Team');
    $invitation = makeInvitation($team, $inviter, $existing->email);

    $response = $this->get(route('invitations.accept', $invitation->token));

    $response->assertRedirect(route('login'));
    expect($existing->teams()->where('teams.id', $team->id)->exists())->toBeTrue();
    expect(Invitation::find($invitation->id))->toBeNull();
});

test('registration with a mismatched email discards the invitation without team attachment', function () {
    config()->set('app.registration_enabled', true);

    $inviter = User::factory()->create();
    $team = makeTeam($inviter, 'Register Team');
    $invitation = makeInvitation($team, $inviter, 'invited@example.com');

    $response = $this->withSession(['valid_invitation' => $invitation])->post('/register', [
        'name' => 'Sneaky User',
        'username' => 'sneakyuser',
        'email' => 'attacker@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect('/dashboard');
    $attacker = User::where('email', 'attacker@example.com')->firstOrFail();
    expect($attacker->teams()->where('teams.id', $team->id)->exists())->toBeFalse();
    expect(session('valid_invitation'))->toBeNull();
});

test('registration with the invited email attaches the new user to the team', function () {
    config()->set('app.registration_enabled', true);

    $inviter = User::factory()->create();
    $team = makeTeam($inviter, 'Register Team');
    $invitation = makeInvitation($team, $inviter, 'invited@example.com');

    $response = $this->withSession(['valid_invitation' => $invitation])->post('/register', [
        'name' => 'Invited User',
        'username' => 'inviteduser',
        'email' => 'invited@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    $response->assertRedirect('/dashboard');
    $newUser = User::where('email', 'invited@example.com')->firstOrFail();
    expect($newUser->teams()->where('teams.id', $team->id)->exists())->toBeTrue();
    expect(Invitation::find($invitation->id))->toBeNull();
});

test('team manager of team A may not update team B, super admin may', function () {
    $teamManager = User::factory()->create();
    $teamManager->roles()->attach(Role::where('name', 'Team Manager')->firstOrFail()->id);

    $superAdmin = User::factory()->create();
    $superAdmin->roles()->attach(Role::where('name', 'Super Admin')->firstOrFail()->id);

    $teamA = makeTeam($teamManager, 'Team A');
    $teamB = makeTeam($superAdmin, 'Team B');

    $this->actingAs($teamManager)
        ->put("/teams/update/{$teamB->id}", ['name' => 'Hijacked', 'description' => 'x'])
        ->assertForbidden();

    expect($teamB->fresh()->name)->toBe('Team B');

    $this->actingAs($teamManager)
        ->put("/teams/update/{$teamA->id}", ['name' => 'Updated A', 'description' => 'x'])
        ->assertOk();

    expect($teamA->fresh()->name)->toBe('Updated A');

    $this->actingAs($superAdmin)
        ->put("/teams/update/{$teamB->id}", ['name' => 'Updated B', 'description' => 'x'])
        ->assertOk();

    expect($teamB->fresh()->name)->toBe('Updated B');
});

function makeToken(User $owner, UserDatabase $database): UserDatabaseToken
{
    return UserDatabaseToken::create([
        'user_id' => $owner->id,
        'database_id' => $database->id,
        'name' => 'test token',
        'full_access_token' => hash('sha256', Str::random(32)),
        'read_only_token' => hash('sha256', Str::random(32)),
        'expiration_day' => 30,
        'created_by' => $owner->id,
    ]);
}

test('token delete requires the delete-database-tokens permission', function () {
    $maintainer = User::factory()->create();
    $maintainer->roles()->attach(Role::where('name', 'Database Maintainer')->firstOrFail()->id);

    $member = User::factory()->create();
    $member->roles()->attach(Role::where('name', 'Member')->firstOrFail()->id);

    $database = UserDatabase::create([
        'user_id' => $maintainer->id,
        'database_name' => 'db-a',
        'is_schema' => '0',
        'created_by' => $maintainer->id,
    ]);

    $token = makeToken($maintainer, $database);

    // Member role carries no delete-database-tokens permission — the
    // permission middleware must refuse, even though the token exists.
    $this->actingAs($member)
        ->delete("/tokens/delete/{$token->id}")
        ->assertForbidden();

    expect(UserDatabaseToken::find($token->id))->not->toBeNull();

    // A database maintainer (global manage-database-tokens permission) may
    // delete tokens across the organisation, by design of the RBAC matrix.
    $this->actingAs($maintainer)
        ->delete("/tokens/delete/{$token->id}")
        ->assertRedirect();

    expect(UserDatabaseToken::find($token->id))->toBeNull();
});
