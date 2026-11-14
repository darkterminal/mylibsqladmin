<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage-teams') ||
            $user->hasPermission('manage-team-members') ||
            $user->hasPermission('view-teams');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage-teams') ||
            $user->hasPermission('create-teams');
    }

    public function update(User $user, Team $team): bool
    {
        return $this->sameTeamOrSuperAdmin($user, $team->id)
            && ($user->hasPermission('manage-teams') ||
                $user->hasPermission('update-teams'));
    }

    public function delete(User $user, Team $team): bool
    {
        return $this->sameTeamOrSuperAdmin($user, $team->id)
            && ($user->hasPermission('manage-teams') ||
                $user->hasPermission('delete-teams'));
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, Team $team): bool
    {
        // Only Super Admin can restore teams
        return $user->hasRole('Super Admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, Team $team): bool
    {
        // Only Super Admin can force delete teams
        return $user->hasRole('Super Admin');
    }

    private function sameTeamOrSuperAdmin(User $user, int $teamId): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return DB::table('team_user')
            ->where('team_id', $teamId)
            ->where('user_id', $user->id)
            ->exists();
    }
}
