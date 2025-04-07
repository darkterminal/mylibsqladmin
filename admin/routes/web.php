<?php

use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DatabaseController;
use App\Http\Controllers\GroupController;
use App\Http\Controllers\TeamController;
use App\Http\Controllers\TokenController;
use App\Models\GroupDatabase;
use App\Models\Team;
use App\Models\User;
use App\Models\UserDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn() => redirect()->route('login'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('database-studio', fn() => Inertia::render('database-studio'))->name('database.studio');

    Route::prefix('dashboard')->group(function () {
        Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

        Route::get('databases', [DashboardController::class, 'indexDatabase'])
            ->name('dashboard.databases')
            ->can('view', [User::class, UserDatabase::class]);

        Route::get('tokens', [DashboardController::class, 'indexToken'])->name('dashboard.tokens');

        Route::get('groups', [DashboardController::class, 'indexGroup'])
            ->name('dashboard.groups')
            ->can('view', [User::class, GroupDatabase::class]);

        Route::get('teams', [DashboardController::class, 'indexTeam'])
            ->name('dashboard.teams')
            ->can('view', [User::class, Team::class]);
    });

    Route::group(['prefix' => 'databases'], function () {
        Route::post('create', [DatabaseController::class, 'createDatabase'])
            ->name('database.create')
            ->can('create', User::class);

        Route::delete('delete/{database}', [DatabaseController::class, 'deleteDatabase'])
            ->name('database.delete')
            ->can('delete', [User::class, UserDatabase::class]);
    });

    Route::group(['prefix' => 'tokens'], function () {
        Route::post('create', [TokenController::class, 'createToken'])
            ->name('token.create');
        Route::delete('delete/{tokenId}', [TokenController::class, 'deleteToken'])->name('token.delete');
    });

    Route::group(['prefix' => 'groups'], function () {
        Route::post('create', [GroupController::class, 'createGroup'])
            ->name('group.create');
        Route::delete('delete/{groupId}', [GroupController::class, 'deleteGroup'])
            ->name('group.delete');
        Route::post('{group}/add-databases', [GroupController::class, 'addDatabasesToGroup'])
            ->name('group.add-databases');
        Route::delete('{group}/delete-database/{database}', [GroupController::class, 'deleteDatabaseFromGroup'])
            ->name('group.delete-databases');
        Route::post('{group}/tokens', [GroupController::class, 'createGroupToken'])
            ->name('group.token.create');
        Route::delete('{tokenId}/tokens', [GroupController::class, 'deleteGroupToken'])
            ->name('group.token.delete');
    });

    Route::group(['prefix' => 'teams'], function () {
        Route::post('create', [TeamController::class, 'createTeam'])
            ->name('team.create')
            ->can('create', 'user');
        Route::put('update/{teamId}', [TeamController::class, 'updateTeam'])
            ->name('team.update')
            ->can('update', ['user', 'team']);
        Route::post('{team}/invitations', [TeamController::class, 'invite'])
            ->name('teams.invitations.store')
            ->can('create', 'user');
        Route::delete('invitations/{invitation}', [TeamController::class, 'revokeInvite'])
            ->name('teams.invitations.destroy')
            ->can('delete', ['user', 'team']);
    });
});

Route::get('invitations/{token}/accept', [TeamController::class, 'acceptInvite'])
    ->name('invitations.accept');

Route::get('mailable/{teamId}', function ($teamId) {
    $team = App\Models\Team::where('id', $teamId)->first();
    $invitation = $team->invitations()->first();
    return (new App\Notifications\TeamInvitation($team, $invitation))->toMail((object) [])->render();
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/api.php';
require __DIR__ . '/trigger.php';
