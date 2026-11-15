<?php

/**
 * End-to-end invitation acceptance funnel:
 * mailable link renders -> accept route behavior per session state.
 */

use App\Models\Invitation;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Str;

uses(\Illuminate\Foundation\Testing\RefreshDatabase::class);

beforeEach(function () {
    \Illuminate\Support\Facades\Artisan::call('db:seed', ['--class' => 'RoleUserSeeder', '--force' => true]);
});

function inviterAndTeam(): array
{
    $inviter = User::factory()->create();
    $team = Team::create(['name' => 'Mailable Team', 'description' => 'm']);

    return [$inviter, $team];
}

function invitationFor(Team $team, string $email): Invitation
{
    $inviter = User::factory()->create();

    return Invitation::create([
        'name' => 'Invitee',
        'email' => $email,
        'token' => Str::random(64),
        'team_id' => $team->id,
        'inviter_id' => $inviter->id,
        'permission_level' => 'member',
        'expires_at' => now()->addDays(7),
        'created_by' => $inviter->id,
    ]);
}

test('the mailable link renders the accept invitation page', function () {
    [$inviter, $team] = inviterAndTeam();
    $invitation = invitationFor($team, 'mailable@example.com');

    $response = $this->get("/mailable/{$team->id}/{$invitation->token}");

    $response->assertOk();
    $response->assertSee('Accept Invitation', false);
});

test('the accept link attaches an authenticated invitee with matching email', function () {
    [$inviter, $team] = inviterAndTeam();

    $invitee = User::factory()->create(['email' => 'invitee@example.com']);
    $team->members()->attach($inviter->id, ['permission_level' => 'super-admin']);

    $invitation = invitationFor($team, 'invitee@example.com');

    $this->actingAs($invitee)
        ->get(route('invitations.accept', $invitation->token))
        ->assertRedirect(route('dashboard.teams'));

    expect($invitee->teams()->where('teams.id', $team->id)->exists())->toBeTrue();
});

test('a guest is redirected to registration when no account exists for the invited email', function () {
    [$inviter, $team] = inviterAndTeam();
    $invitation = invitationFor($team, 'brand-new@example.com');

    $this->get(route('invitations.accept', $invitation->token))
        ->assertRedirect(route('register'));
});

test('registering with the invited email completes the attach', function () {
    config()->set('app.registration_enabled', true);

    [$inviter, $team] = inviterAndTeam();
    $team->members()->attach($inviter->id, ['permission_level' => 'super-admin']);

    $invitation = invitationFor($team, 'join-me@example.com');

    $response = $this->withSession(['valid_invitation' => $invitation])
        ->post('/register', [
            'name' => 'Join Me',
            'username' => 'joinme',
            'email' => 'join-me@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

    $response->assertRedirect('/dashboard');

    $user = User::where('email', 'join-me@example.com')->firstOrFail();
    expect($user->teams()->where('teams.id', $team->id)->exists())->toBeTrue();
    expect(Invitation::find($invitation->id))->toBeNull();
});
