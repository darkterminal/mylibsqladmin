<?php

namespace App\Events;

use Illuminate\Foundation\Events\Dispatchable;

class TeamDatabasesRequested
{
    use Dispatchable;

    public $userId;
    public $teamId;

    public function __construct($userId, $teamId)
    {
        $this->userId = $userId;
        $this->teamId = $teamId;
    }
}
