<?php

use App\Http\Controllers\Cli\CliCreateNewDatabaseController;
use App\Http\Controllers\Cli\CliDatabaseArchiveController;
use App\Http\Controllers\Cli\CliDatabaseListController;
use App\Http\Controllers\Cli\CliDeleteDatabaseController;
use App\Http\Controllers\Cli\CliForceDeleteDatabaseController;
use App\Http\Controllers\Cli\CliGetTokenByDatabaseNameController;
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
use App\Http\Controllers\Cli\CliTeamFinderController;
use App\Http\Controllers\Cli\CliTeamListController;
use App\Http\Controllers\Cli\CliUserListController;
use Illuminate\Http\Request;

Route::group(['prefix' => '/api/cli'], function () {
    Route::post('/login', CliLoginController::class);
    Route::post('/logout', CliLogoutController::class);

    Route::middleware(['auth:sanctum', 'ensureCliRequest'])->group(function () {
        Route::group(['prefix' => '/db'], function () {
            Route::post('/create', CliCreateNewDatabaseController::class);
            Route::delete('/delete/{database_name}', CliDeleteDatabaseController::class);
            Route::get('/archives', CliDatabaseArchiveController::class);
            Route::post('/restore/{database_name}', CliResotreDatabaseController::class);
            Route::delete('/force-delete/{database_name}', CliForceDeleteDatabaseController::class);
            Route::get('/token/{database_name}', CliGetTokenByDatabaseNameController::class);
            Route::get('/lists', CliDatabaseListController::class);
        });

        Route::group(['prefix' => '/group'], function () {
            Route::get('/lists', CliGroupListController::class);
            Route::post('/create', CliGroupCreateController::class);
            Route::delete('/delete/{group_id}', CliGroupDeleteController::class);
        });

        Route::group(['prefix' => '/team'], function () {
            Route::get('/lists', CliTeamListController::class);
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
