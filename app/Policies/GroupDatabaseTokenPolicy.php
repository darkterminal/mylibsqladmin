<?php

namespace App\Policies;

use App\Models\User;

class GroupDatabaseTokenPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage-group-tokens') ||
            $user->hasPermission('view-group-tokens');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage-group-tokens') ||
            $user->hasPermission('create-group-tokens');
    }

    public function update(User $user): bool
    {
        return $user->hasPermission('manage-group-tokens') ||
            $user->hasPermission('update-group-tokens');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('manage-group-tokens') ||
            $user->hasPermission('delete-group-tokens');
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
