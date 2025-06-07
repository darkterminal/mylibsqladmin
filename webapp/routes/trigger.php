<?php

use App\Events\TriggerDatabaseStatsChangeEvent;
use App\Http\Controllers\ActivityLogController;

Route::middleware('auth')->group(function () {
    Route::get('/trigger-stats-changed/{databaseName?}/{source?}', function ($databaseName, $source = 'unknown') {
        event(new TriggerDatabaseStatsChangeEvent($databaseName, $source));
        logger("Triggering stats changed for {$databaseName} from $source");
        return "Stats changed for {$databaseName}";
    })->name('trigger.stats-changed');
    Route::post('/activities', [ActivityLogController::class, 'store'])->name('activities.store');
});
