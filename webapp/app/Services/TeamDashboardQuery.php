<?php

namespace App\Services;

use App\Models\Team;

/**
 * Read-side decoration for the teams dashboard pages — the shape both the
 * live and archived lists must render. Kept separate so rendering and
 * querying evolve independently of the command side (TeamMembershipService).
 */
class TeamDashboardQuery
{
    public static function determineDatabaseType($isSchema): string
    {
        if (is_numeric($isSchema) && (int) $isSchema === 1) {
            return 'schema';
        }

        if (is_numeric($isSchema) && (int) $isSchema === 0) {
            return 'standalone';
        }

        return 'child';
    }

    public static function pendingInvitations(Team $team): array
    {
        return $team->invitations
            ->where('expires_at', '>', now())
            ->map(fn ($invite) => [
                'id' => $invite->id,
                'name' => $invite->name,
                'email' => $invite->email,
                'inviter' => $invite->inviter->name,
                'expires_at' => $invite->expires_at->diffForHumans(),
                'permission_level' => $invite->permission_level,
                'sent_at' => $invite->created_at->format('M d, Y H:i'),
            ])
            ->values()
            ->toArray();
    }

    public static function teamMembers(Team $team): array
    {
        return $team->members->map(fn ($member) => [
            'id' => $member->id,
            'name' => $member->name,
            'email' => $member->email,
            'role' => $member->pivot->permission_level,
        ])->all();
    }

    public static function groups($groups): array
    {
        return $groups->map(fn ($group) => [
            'id' => $group->id,
            'name' => $group->name,
            'databases' => $group->members->map(fn ($database) => [
                'id' => $database->id,
                'name' => $database->database_name,
                'type' => self::determineDatabaseType($database->is_schema),
                'lastActivity' => $database->latestActivity?->created_at->diffForHumans() ?? 'No activity',
            ])->values()->all(),
        ])->values()->all();
    }

    public static function recentActivities($activities): array
    {
        return $activities->map(fn ($activity) => [
            'id' => $activity->id,
            'user' => $activity->user?->name,
            'action' => $activity->action,
            'database' => $activity->database?->database_name,
            'time' => $activity->created_at->diffForHumans(),
        ])->values()->all();
    }
}
