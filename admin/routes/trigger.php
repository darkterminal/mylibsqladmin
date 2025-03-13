<?php

use App\Events\TriggerDatabaseStatsChangeEvent;

Route::middleware('auth')->group(function () {
    Route::get('/trigger-stats-changed/{databaseName?}', function ($databaseName) {
        event(new TriggerDatabaseStatsChangeEvent($databaseName));
        return "Stats changed for {$databaseName}";
    })->name('trigger.stats-changed');
});
