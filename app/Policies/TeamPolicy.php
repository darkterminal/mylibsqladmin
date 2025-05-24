<?php
namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage-teams') || $user->hasPermission('manage-team-members') ||
            $user->hasPermission('view-teams');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage-teams') ||
            $user->hasPermission('create-teams');
    }

    public function update(User $user): bool
    {
        return $user->hasPermission('manage-teams') ||
            $user->hasPermission('update-teams');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('manage-teams') ||
            $user->hasPermission('delete-teams');
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
}
