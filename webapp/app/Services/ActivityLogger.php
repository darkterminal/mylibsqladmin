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
    ): void {
        $action = ActivityLog::determineAction($query, $databaseId);

        ActivityLog::create([
            'team_id' => $teamId,
            'user_id' => $userId,
            'database_id' => $databaseId,
            'action' => $action
        ]);

        $location = get_ip_location(request()->ip());
        $user = auth()->user();

        log_user_activity(
            $user,
            ActivityType::DATABASE_STUDIO_ACTIVITY,
            $user->name . ' ' . $action . ' from ' . request()->ip(),
            [
                'ip' => request()->ip(),
                'device' => request()->userAgent(),
                'country' => $location['country'],
                'city' => $location['city'],
            ]
        );
    }
}
