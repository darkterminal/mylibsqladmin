<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserDatabase;
use Illuminate\Auth\Access\Response;

class UserDatabasePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Super Admin or users with access-team-databases permission
        return $user->hasRole('Super Admin') ||
            $user->can('access-team-databases');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, UserDatabase $database): bool
    {
        // Check for Database Maintainer permission
        if ($user->hasRole('Database Maintainer') || $user->hasRole('Super Admin')) {
            return $user->can('access-team-databases');
        }

        // Database owner, Super Admin, or team members with access
        return $user->ownsDatabase($database) ||
            $user->hasRole('Super Admin') ||
            ($user->hasTeamAccessToDatabase($database) &&
                $user->can('access-team-databases'));
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Users with database creation permissions or Super Admin
        return $user->hasRole('Super Admin') ||
            $user->can('manage-group-databases') ||
            $user->can('manage-database-tokens') ||
            ($user->hasRole('Database Maintainer') && $user->can('access-team-databases'));
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, UserDatabase $database): bool
    {
        // Owner/Super Admin with appropriate permissions
        return ($user->ownsDatabase($database) ||
            $user->hasRole('Super Admin')) &&
            ($database->is_group_database ?
                $user->can('manage-group-databases') :
                $user->can('manage-database-tokens'));
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, UserDatabase $database): bool
    {
        // Owner/Super Admin with appropriate permissions
        return ($user->ownsDatabase($database) ||
            $user->hasRole('Super Admin')) &&
            ($database->is_group_database ?
                $user->can('manage-group-databases') :
                $user->can('manage-database-tokens'));
    }

    /**
     * Determine whether the user can manage tokens.
     */
    public function manageTokens(User $user, UserDatabase $database): bool
    {
        // Token management specific permission check
        return $user->hasRole('Super Admin') ||
            ($user->ownsDatabase($database) &&
                $user->can('manage-database-tokens')) ||
            ($user->hasTeamAccessToDatabase($database) &&
                $user->can('manage-group-database-tokens'));
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, UserDatabase $database): bool
    {
        // Only Super Admin can restore
        return $user->hasRole('Super Admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, UserDatabase $database): bool
    {
        // Only Super Admin can force delete
        return $user->hasRole('Super Admin');
    }
}
