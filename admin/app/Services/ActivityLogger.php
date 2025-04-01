<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Team;
use App\Models\User;

class ActivityLogger
{
    public static function log(
        int $teamId,
        int $userId,
        int $databaseId,
        string $query
    ): void {
        ActivityLog::create([
            'team_id' => $teamId,
            'user_id' => $userId,
            'database_id' => $databaseId,
            'action' => ActivityLog::determineAction($query)
        ]);
    }
}
