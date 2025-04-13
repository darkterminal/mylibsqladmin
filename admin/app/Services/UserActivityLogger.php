<?php

namespace App\Services;

use App\Models\User;
use App\Models\UserActivityLog;
use Illuminate\Support\Collection;

class UserActivityLogger
{
    /**
     * Record a new user activity
     */
    public static function record(
        User $user,
        string $type,
        string $description,
        ?array $metadata = null
    ): void {
        try {
            UserActivityLog::create([
                'user_id' => $user->id,
                'type' => $type,
                'description' => $description,
                'metadata' => $metadata,
                'timestamp' => now()
            ]);
        } catch (\Exception $e) {
            \Log::error('Failed to record activity log: ' . $e->getMessage());
        }
    }

    /**
     * Get activities for a specific user
     */
    public static function getActivitiesForUser(
        User $user,
        int $limit = null
    ): Collection {
        $query = UserActivityLog::whereUserId($user->id)
            ->with('user')
            ->orderByDesc('timestamp');

        if ($limit) {
            $query->take($limit);
        }

        return $query->get();
    }

    /**
     * Get activities by type for a user
     */
    public static function getActivitiesByType(
        User $user,
        string $type,
        int $limit = null
    ): Collection {
        $query = UserActivityLog::where([
            'user_id' => $user->id,
            'type' => $type
        ])
            ->orderByDesc('timestamp');

        if ($limit) {
            $query->take($limit);
        }

        return $query->get();
    }
}
