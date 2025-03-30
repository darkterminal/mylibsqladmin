<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', fn() => redirect()->route('login'))->name('home');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::prefix('dashboard')->group(function () {
        Route::get('/', [App\Http\Controllers\DashboardController::class, 'index'])->name('dashboard');
        Route::get('tokens', [App\Http\Controllers\DashboardController::class, 'indexToken'])->name('dashboard.tokens');
        Route::get('groups', [App\Http\Controllers\DashboardController::class, 'indexGroup'])->name('dashboard.groups');
        Route::get('teams', [App\Http\Controllers\DashboardController::class, 'indexTeams'])->name('dashboard.teams');
    });

    Route::group(['prefix' => 'databases'], function () {
        Route::post('create', [App\Http\Controllers\DashboardController::class, 'createDatabase'])->name('database.create');
        Route::delete('delete/{database}', [App\Http\Controllers\DashboardController::class, 'deleteDatabase'])->name('database.delete');
    });

    Route::group(['prefix' => 'tokens'], function () {
        Route::post('create', [App\Http\Controllers\DashboardController::class, 'createToken'])->name('token.create');
        Route::delete('delete/{tokenId}', [App\Http\Controllers\DashboardController::class, 'deleteToken'])->name('token.delete');
    });

    Route::group(['prefix' => 'groups'], function () {
        Route::post('create', [App\Http\Controllers\DashboardController::class, 'createGroup'])->name('group.create');
        Route::delete('delete/{groupId}', [App\Http\Controllers\DashboardController::class, 'deleteGroup'])->name('group.delete');
        Route::post('{group}/add-databases', [App\Http\Controllers\DashboardController::class, 'addDatabasesToGroup'])->name('group.add-databases');
        Route::delete('{group}/delete-database/{database}', [App\Http\Controllers\DashboardController::class, 'deleteDatabaseFromGroup'])->name('group.delete-databases');
        Route::post('{group}/tokens', [App\Http\Controllers\DashboardController::class, 'createGroupToken'])->name('group.token.create');
        Route::delete('{tokenId}/tokens', [App\Http\Controllers\DashboardController::class, 'deleteGroupToken'])->name('group.token.delete');
    });
});

require __DIR__ . '/settings.php';
require __DIR__ . '/auth.php';
require __DIR__ . '/api.php';
require __DIR__ . '/trigger.php';
