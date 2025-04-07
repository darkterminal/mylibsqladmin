<?php
namespace App\Policies;

use App\Models\Team;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class TeamPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Anyone can view the teams list, but the controller will filter what they see
        return true;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, Team $team): bool
    {
        // Super Admin can view any team
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Users can view teams they're members of
        return $team->members()->where('user_id', $user->id)->exists();
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Only Super Admin and users with create-teams permission can create teams
        return $user->hasRole('Super Admin') || $user->hasPermissionTo('create-teams');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, Team $team): bool
    {
        // Super Admin can update any team
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Team Manager can update teams they're members of
        if ($user->hasPermissionTo('manage-teams')) {
            return $team->members()->where('user_id', $user->id)->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, Team $team): bool
    {
        // Only Super Admin can delete teams
        return $user->hasRole('Super Admin');
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

    /**
     * Determine whether the user can manage team members.
     */
    public function manageMembers(User $user, Team $team): bool
    {
        // Super Admin can manage any team members
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Team Manager can manage members of teams they belong to
        if ($user->hasPermissionTo('manage-teams')) {
            return $team->members()->where('user_id', $user->id)->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can manage team groups.
     */
    public function manageGroups(User $user, Team $team): bool
    {
        // Super Admin can manage any team's groups
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Users with the manage-team-groups permission can manage groups of teams they belong to
        if ($user->hasPermissionTo('manage-team-groups')) {
            return $team->members()->where('user_id', $user->id)->exists();
        }

        return false;
    }

    /**
     * Determine whether the user can access team databases.
     */
    public function accessDatabases(User $user, Team $team): bool
    {
        // Super Admin can access any team's databases
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Users with access-team-databases permission can access databases of teams they belong to
        if ($user->hasPermissionTo('access-team-databases')) {
            return $team->members()->where('user_id', $user->id)->exists();
        }

        return false;
    }
}
