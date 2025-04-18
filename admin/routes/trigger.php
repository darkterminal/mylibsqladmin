<?php

use App\Events\TriggerDatabaseStatsChangeEvent;
use App\Http\Controllers\ActivityLogController;

Route::middleware('auth')->group(function () {
    Route::get('/trigger-stats-changed/{databaseName?}', function ($databaseName) {
        event(new TriggerDatabaseStatsChangeEvent($databaseName));
        return "Stats changed for {$databaseName}";
    })->name('trigger.stats-changed');
    Route::post('/activities', [ActivityLogController::class, 'store'])->name('activities.store');
});

Route::post('/health', fn() => response()->json(['status' => 'ok']));
