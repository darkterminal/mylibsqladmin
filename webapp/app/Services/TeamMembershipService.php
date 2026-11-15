<?php

namespace App\Services;

use App\ActivityType;
use App\Jobs\SendTeamInvitation;
use App\Models\Invitation;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Command side of team membership: attach/detach, role changes and the
 * invitation lifecycle. Read paths stay in TeamController.
 */
class TeamMembershipService
{
    public function storeMember(Team $team, int $userId, string $permissionLevel): void
    {
        $team->members()->syncWithoutDetaching([
            $userId => ['permission_level' => $permissionLevel],
        ]);

        Team::refreshLatestTeamDatabases((int) auth()->id());

        log_user_activity(
            auth()->user(),
            ActivityType::TEAM_MEMBER_CREATE,
            "Team member added to {$team->name} from " . request()->ip(),
            activity_request_context()
        );
    }

    public function detachMember(Team $team, User $member): void
    {
        $team->members()->detach($member->id);

        Team::refreshLatestTeamDatabases((int) auth()->id());

        log_user_activity(
            auth()->user(),
            ActivityType::TEAM_MEMBER_DELETE,
            "Team member {$member->name} deleted from " . request()->ip(),
            activity_request_context()
        );
    }

    public function updateMemberRole(Team $team, User $member, string $role): void
    {
        // Update team membership pivot
        $team->members()->updateExistingPivot($member->id, [
            'permission_level' => $role,
        ]);

        // Clear cached permissions
        cache()->forget("user-{$member->id}-permissions");

        // Refresh team data in session
        Team::setTeamDatabases($member->id, $team->id);

        log_user_activity(
            auth()->user(),
            ActivityType::TEAM_MEMBER_UPDATE,
            "Team member {$member->name} role updated from " . request()->ip(),
            activity_request_context()
        );
    }

    public function invite(Team $team, ?string $name, string $email, string $role): Invitation
    {
        $invitation = $team->invitations()->create([
            'name' => $name ?: strtok($email, '@'),
            'email' => $email,
            'token' => Str::random(64),
            'inviter_id' => auth()->id(),
            'permission_level' => $role,
            'expires_at' => now()->addDays(7),
            'created_by' => auth()->id(),
        ]);

        SendTeamInvitation::dispatch($invitation);

        log_user_activity(
            auth()->user(),
            ActivityType::TEAM_MEMBER_CREATE,
            "Invitation to {$email} sent to team {$team->name} from " . request()->ip(),
            activity_request_context()
        );

        return $invitation;
    }

    public function acceptInvitation(Invitation $invitation, User|Authenticatable $user): void
    {
        $team = $invitation->team;

        $team->members()->syncWithoutDetaching([
            $user->id => [
                'permission_level' => $invitation->permission_level,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        Team::setTeamDatabases($user->id, $team->id);

        log_user_activity(
            $user,
            ActivityType::TEAM_MEMBER_CREATE,
            "Joined team {$team->name} from " . request()->ip(),
            activity_request_context()
        );
    }

    public function revokeInvitation(Invitation $invitation): void
    {
        $email = $invitation->email;
        $invitation->delete();

        log_user_activity(
            auth()->user(),
            ActivityType::TEAM_MEMBER_DELETE,
            "Invitation to {$email} revoked from " . request()->ip(),
            activity_request_context()
        );
    }
}
