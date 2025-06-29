<?php

use App\Http\Controllers\Cli\CliDatabaseListController;
use App\Http\Controllers\Cli\CliLoginController;
use App\Http\Controllers\Cli\CliLogoutController;
use App\Http\Controllers\Cli\CliUserListController;
use App\Http\Controllers\GroupDatabaseController;
use App\Http\Controllers\SubdomainValidationController;
use App\Http\Controllers\TeamController;
use App\Services\SqldService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

Route::middleware('auth')->group(function () {
    Route::get('/api/databases', function () {
        try {
            $localDbs = SqldService::getDatabases(local: false);

            $transformed = array_map(fn($db) => [
                'name' => $db['database_name'],
                'status' => $db['deleted_at'] != null ? 'inactive' : 'active',
                'path' => $db['database_name'],
            ], $localDbs);

            return response()->json([
                'databases' => $transformed
            ]);
        } catch (Exception $e) {
            Log::error('Error in API /api/databases: ' . $e->getMessage());
            return response()->json([
                'error' => 'Failed to fetch databases',
            ], 500);
        }
    });


    Route::post('/api/group/create-only', [GroupDatabaseController::class, 'createGroupOnly'])->name('api.group.create-only');
    Route::get('/api/teams/{teamId}/databases', [TeamController::class, 'getDatabases'])->name('api.teams.databases');
    Route::post('/api/check-gate', function (Request $request) {
        $model = $request->model_type::findOrFail($request->model_id);

        return response()->json([
            'allowed' => Gate::allows($request->ability, $model)
        ]);
    })->name('api.check-gate');
});

Route::group(['prefix' => '/api/cli', 'middleware' => 'ensureCliRequest'], function () {
    Route::post('/login', CliLoginController::class);
    Route::post('/logout', CliLogoutController::class);
    Route::middleware('auth:sanctum')->group(function () {
        Route::get('/db/lists', CliDatabaseListController::class);
        Route::get('/user/lists', CliUserListController::class);
    });
});

Route::get('/validate-subdomain', [SubdomainValidationController::class, 'validateSubdomain']);
