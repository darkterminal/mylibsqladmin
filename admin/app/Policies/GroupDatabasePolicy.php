<?php

namespace App\Policies;

use App\Models\GroupDatabase;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GroupDatabasePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasRole('Super Admin') ||
            $user->can('access-team-databases');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, GroupDatabase $groupDatabase): bool
    {
        // Super Admin can view anything
        if ($user->hasRole('Super Admin'))
            return true;

        // Team members with access permission
        return $groupDatabase->team->hasMember($user) &&
            $user->can('access-team-databases');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasRole('Super Admin') ||
            $user->can('manage-group-databases');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, GroupDatabase $groupDatabase): bool
    {
        return $user->hasRole('Super Admin') ||
            ($groupDatabase->team->hasMember($user) &&
                $user->can('manage-group-databases'));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, GroupDatabase $groupDatabase): bool
    {
        return $user->hasRole('Super Admin') ||
            ($groupDatabase->team->hasMember($user) &&
                $user->can('manage-group-databases'));
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, GroupDatabase $groupDatabase): bool
    {
        return $this->delete($user, $groupDatabase);
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, GroupDatabase $groupDatabase): bool
    {
        return $user->hasRole('Super Admin');
    }

    /**
     * Determine whether the user can manage tokens for the group database.
     */
    public function manageTokens(User $user, GroupDatabase $groupDatabase): bool
    {
        // Super Admin or Database Maintainer with team access
        return $user->hasRole('Super Admin') ||
            ($groupDatabase->team->hasMember($user) &&
                $user->can('manage-group-database-tokens'));
    }

    /**
     * Determine whether the user can view database tokens.
     */
    public function viewTokens(User $user, GroupDatabase $groupDatabase): bool
    {
        return $this->manageTokens($user, $groupDatabase) ||
            ($groupDatabase->team->hasMember($user) &&
                $user->can('access-team-databases'));
    }
}
