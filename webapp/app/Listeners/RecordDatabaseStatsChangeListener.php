<?php

namespace App\Listeners;

use App\Events\TriggerDatabaseStatsChangeEvent;
use App\Services\StatsFetcherService;

class RecordDatabaseStatsChangeListener
{
    /**
     * Create the event listener.
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(TriggerDatabaseStatsChangeEvent $event): void
    {
        StatsFetcherService::run($event->databaseName, $event->source, $event->userId);
    }
}
