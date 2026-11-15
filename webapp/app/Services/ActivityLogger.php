<?php

namespace App\Services;

use App\ActivityType;
use App\Models\ActivityLog;

class ActivityLogger
{
    public static function log(
        int $teamId,
        int $userId,
        int $databaseId,
        string $query
    ): string {
        $action = ActivityLog::determineAction($query, $databaseId);

        ActivityLog::create([
            'team_id' => $teamId,
            'user_id' => $userId,
            'database_id' => $databaseId,
            'action' => $action
        ]);

        $user = auth()->user();

        log_user_activity(
            $user,
            ActivityType::DATABASE_STUDIO_ACTIVITY,
            $user->name . ' ' . $action . ' from ' . request()->ip(),
            activity_request_context()
        );

        return $action;
    }
}
