<?php

namespace App\Listeners;

use App\Events\TeamDatabasesRequested;
use App\Models\Team;

class FetchTeamDatabases
{
    public function handle(TeamDatabasesRequested $event)
    {
        Team::setTeamDatabases($event->userId, $event->teamId);
    }
}
