<?php

use App\Http\Controllers\GroupDatabaseController;
use App\Http\Controllers\SubdomainValidationController;
use App\Http\Controllers\TeamController;

Route::middleware('auth')->group(function () {
    Route::post('/api/group/create-only', [GroupDatabaseController::class, 'createGroupOnly'])->name('api.group.create-only');
    Route::get('/api/teams/{teamId}/databases', [TeamController::class, 'getDatabases'])->name('api.teams.databases');
});

Route::get('/validate-subdomain', [SubdomainValidationController::class, 'validateSubdomain']);
