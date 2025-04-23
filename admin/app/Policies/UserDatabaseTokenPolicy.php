<?php

namespace App\Policies;

use App\Models\User;

class UserDatabaseTokenPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage-database-tokens') ||
            $user->hasPermission('view-database-tokens');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage-database-tokens') ||
            $user->hasPermission('create-database-tokens');
    }

    public function update(User $user): bool
    {
        return $user->hasPermission('manage-database-tokens') ||
            $user->hasPermission('update-database-tokens');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('manage-database-tokens') ||
            $user->hasPermission('delete-database-tokens');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user): bool
    {
        return $user->hasRole('Super Admin');
    }
}
