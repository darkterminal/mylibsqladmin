<?php

use App\Events\TriggerDatabaseStatsChangeEvent;
use App\Http\Controllers\ActivityLogController;

Route::middleware('auth')->group(function () {
    Route::get('/trigger-stats-changed/{databaseName?}/{source?}', function ($databaseName, $source = 'unknown') {
        $userId = auth()->user()->id;
        event(new TriggerDatabaseStatsChangeEvent($databaseName, $source, $userId));
        logger("Triggering stats changed for {$databaseName} from $source by user $userId");
        return "Stats changed for {$databaseName}";
    })->name('trigger.stats-changed');
    Route::post('/activities', [ActivityLogController::class, 'store'])->name('activities.store');
});
