<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\UserDatabaseController;
use App\Http\Controllers\GroupDatabaseController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TokenController;
use App\Models\GroupDatabase;
use App\Models\Team;
use App\Models\User;
use App\Models\UserDatabase;
use App\Models\UserDatabaseToken;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn() => redirect()->route('login'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('database-studio', fn() => Inertia::render('database-studio'))->name('database.studio');

    Route::prefix('dashboard')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('databases', [UserDatabaseController::class, 'index'])
            ->name('dashboard.databases')
            ->can('viewAny', UserDatabase::class);
        Route::get('database-archived', [UserDatabaseController::class, 'archived'])
            ->name('dashboard.database-archived')
            ->can('viewAny', UserDatabase::class);

        Route::get('tokens', [TokenController::class, 'index'])
            ->name('dashboard.tokens')
            ->can('viewAny', UserDatabaseToken::class);

        Route::get('groups', [GroupDatabaseController::class, 'index'])
            ->name('dashboard.groups')
            ->can('viewAny', GroupDatabase::class);

        Route::get('teams', [TeamController::class, 'index'])
            ->name('dashboard.teams')
            ->can('viewAny', Team::class);

        Route::get('users', [UserController::class, 'index'])
            ->name('dashboard.users')
            ->can('viewAny', User::class);
    });

    Route::group(['prefix' => 'databases'], function () {
        Route::post('create', [UserDatabaseController::class, 'createDatabase'])
            ->name('database.create')
            ->can('create', UserDatabase::class);

        Route::delete('delete/{database}', [UserDatabaseController::class, 'deleteDatabase'])
            ->name('database.delete')
            ->can('delete', UserDatabase::class);

        Route::post('restore', [UserDatabaseController::class, 'restoreDatabase'])
            ->name('database.restore')
            ->can('delete', UserDatabase::class);

        Route::delete('force-delete/{database}', [UserDatabaseController::class, 'forceDeleteDatabase'])
            ->name('database.force-delete')
            ->can('delete', UserDatabase::class);
    });

    Route::group(['prefix' => 'tokens'], function () {
        Route::post('create', [TokenController::class, 'createToken'])
            ->name('token.create');
        Route::delete('delete/{tokenId}', [TokenController::class, 'deleteToken'])
            ->name('token.delete');
    });

    Route::group(['prefix' => 'groups'], function () {
        Route::post('create', [GroupDatabaseController::class, 'createGroup'])
            ->name('group.create');
        Route::delete('delete/{groupId}', [GroupDatabaseController::class, 'deleteGroup'])
            ->name('group.delete');
        Route::post('{group}/add-databases', [GroupDatabaseController::class, 'addDatabasesToGroup'])
            ->name('group.add-databases');
        Route::delete('delete-database/{database}', [GroupDatabaseController::class, 'deleteDatabaseFromGroup'])
            ->name('group.delete-databases');
        Route::post('{group}/tokens', [GroupDatabaseController::class, 'createGroupToken'])
            ->name('group.token.create');
        Route::delete('{tokenId}/tokens', [GroupDatabaseController::class, 'deleteGroupToken'])
            ->name('group.token.delete');
    });

    Route::group(['prefix' => 'teams'], function () {
        Route::post('create', [TeamController::class, 'createTeam'])
            ->name('team.create')
            ->can('create', Team::class);

        Route::put('update/{teamId}', [TeamController::class, 'updateTeam'])
            ->name('team.update')
            ->can('update', Team::class);

        Route::put('/teams/{team}/users/{user}/role', [TeamController::class, 'updateTeamMemberRole'])
            ->name('teams.members.update-role')
            ->can('update', Team::class);

        Route::delete('delete/{teamId}', [TeamController::class, 'deleteTeam'])
            ->name('team.delete')
            ->can('delete', Team::class);

        Route::delete('{team}/users/{user}/remove', [TeamController::class, 'deleteTeamMember'])
            ->name('teams.members.delete')
            ->can('delete', Team::class);

        Route::post('{team}/invitations', [TeamController::class, 'invite'])
            ->name('teams.invitations.store')
            ->can('create', Team::class);

        Route::delete('invitations/{invitation}', [TeamController::class, 'revokeInvite'])
            ->name('teams.invitations.destroy')
            ->can('delete', Team::class);
    });

    Route::group(['prefix' => 'users'], function () {
        Route::get('{user}/detail', [UserController::class, 'show'])
            ->name('user.show')
            ->can('view', User::class);

        Route::get('create', [UserController::class, 'create'])
            ->name('user.create')
            ->can('create', User::class);

        Route::post('create', [UserController::class, 'store'])
            ->name('user.store')
            ->can('create', User::class);

        Route::get('{user}/edit', [UserController::class, 'edit'])
            ->name('user.edit')
            ->can('update', User::class);

        Route::put('{user}/update', [UserController::class, 'update'])
            ->name('user.update')
            ->can('update', User::class);

        Route::delete('{user}/delete', [UserController::class, 'destroy'])
            ->name('user.delete')
            ->can('delete', User::class);

        Route::get('archive', [UserController::class, 'archive'])
            ->name('user.archive')
            ->can('delete', User::class);

        Route::put('{user}/restore', [UserController::class, 'restoreUser'])
            ->name('user.restore')
            ->withTrashed()
            ->can('restore', User::class);

        Route::delete('{user}/force-delete', [UserController::class, 'forceDelete'])
            ->name('user.force-delete')
            ->withTrashed()
            ->can('forceDelete', User::class);

        Route::put('{user}/deactivate', [UserController::class, 'deactivate'])
            ->name('user.deactivate')
            ->can('update', User::class);

        Route::put('{user}/activate', [UserController::class, 'activate'])
            ->name('user.reactivate')
            ->can('update', User::class);

        Route::get('{user}/activities', [UserController::class, 'activities'])
            ->name('user.activities')
            ->can('view', User::class);
    });
});

Route::group(['prefix' => 'invitations'], function () {
    Route::get('{token}/accept', [TeamController::class, 'acceptInvite'])
        ->name('invitations.accept')
        ->middleware('invitationExpiration');

    Route::get('expired', fn() => Inertia::render('errors/invitation-expired'))
        ->name('invitation.expired');
});

Route::get('mailable/{teamId}/{token}', function ($teamId, $token) {
    $team = Team::with([
        'invitations' => function ($query) use ($token) {
            $query->where('token', $token);
        }
    ])->findOrFail($teamId);

    $invitation = $team->invitations->first();

    if (!$invitation || $invitation->team_id != $team->id) {
        abort(404, 'Invitation not found');
    }

    return (new App\Notifications\TeamInvitation($team, $invitation))
        ->toMail((object) [])
        ->render();
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/api.php';
require __DIR__ . '/api.cli.php';
require __DIR__ . '/trigger.php';
