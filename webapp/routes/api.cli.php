<?php

use App\Http\Controllers\Cli\CliCreateNewDatabaseController;
use App\Http\Controllers\Cli\CliDatabaseArchiveController;
use App\Http\Controllers\Cli\CliDatabaseListController;
use App\Http\Controllers\Cli\CliDeleteDatabaseController;
use App\Http\Controllers\Cli\CliForceDeleteDatabaseController;
use App\Http\Controllers\Cli\CliGetTokenByDatabaseNameController;
use App\Http\Controllers\Cli\CliMintDatabaseTokenController;
use App\Http\Controllers\Cli\CliGroupCreateController;
use App\Http\Controllers\Cli\CliGroupDeleteController;
use App\Http\Controllers\Cli\CliGroupListController;
use App\Http\Controllers\Cli\CliLoginController;
use App\Http\Controllers\Cli\CliLogoutController;
use App\Http\Controllers\Cli\CliResotreDatabaseController;
use App\Http\Controllers\Cli\CliTeamCreateController;
use App\Http\Controllers\Cli\CliTeamDeleteController;
use App\Http\Controllers\Cli\CliTeamDetailController;
use App\Http\Controllers\Cli\CliTeamEditController;
use App\Http\Controllers\Cli\CliTeamArchiveListController;
use App\Http\Controllers\Cli\CliTeamFinderController;
use App\Http\Controllers\Cli\CliTeamListController;
use App\Http\Controllers\Cli\CliTeamRestoreController;
use App\Http\Controllers\Cli\CliUserListController;
use App\Http\Middleware\EnsureActiveUser;
use Illuminate\Http\Request;

Route::group(['prefix' => '/api/cli'], function () {
    Route::post('/login', CliLoginController::class);
    Route::post('/logout', CliLogoutController::class);

    // This file is required from routes/web.php, so web-group middleware also
    // applies. EnsureActiveUser is session-specific (auth()->logout() + session
    // flush) and must not run on token-authenticated CLI routes; the deactivation
    // gate lives in EnsureCliRequest instead.
    Route::middleware(['auth:sanctum', 'ensureCliRequest'])
        ->withoutMiddleware(EnsureActiveUser::class)
        ->group(function () {
        Route::group(['prefix' => '/db'], function () {
            Route::post('/create', CliCreateNewDatabaseController::class);
            Route::delete('/delete/{database_name}', CliDeleteDatabaseController::class);
            Route::get('/archives', CliDatabaseArchiveController::class);
            Route::post('/restore/{database_name}', CliResotreDatabaseController::class);
            Route::delete('/force-delete/{database_name}', CliForceDeleteDatabaseController::class);
            // GET /api/cli/db/token/{database_name}
            Route::get('/token/{database_name}', CliGetTokenByDatabaseNameController::class);
            Route::post('/token/mint/{database_name}', CliMintDatabaseTokenController::class);
            Route::get('/lists', CliDatabaseListController::class);
        });

        Route::group(['prefix' => '/group'], function () {
            Route::get('/lists', CliGroupListController::class);
            Route::post('/create', CliGroupCreateController::class);
            Route::delete('/delete/{group_id}', CliGroupDeleteController::class);
        });

        Route::group(['prefix' => '/team'], function () {
            Route::get('/lists', CliTeamListController::class);
            // GET /api/cli/team/archives
            Route::get('/archives', CliTeamArchiveListController::class);
            // POST /api/cli/team/restore/{team_id}
            Route::post('/restore/{team_id}', CliTeamRestoreController::class);
            // GET /api/cli/team/find/{team_name}
            Route::get('/find/{team_name}', CliTeamFinderController::class);
            Route::get('/get/{team_id}', CliTeamDetailController::class);
            Route::post('/create', CliTeamCreateController::class);
            Route::put('/update/{team_id}', CliTeamEditController::class);
            Route::delete('/delete/{team_id}', CliTeamDeleteController::class);
        });

        Route::group(['prefix' => '/user'], function () {
            Route::get('/lists', CliUserListController::class);
        });
    });
});
