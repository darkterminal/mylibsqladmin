<?php

namespace App\Policies;

use App\Models\User;

class GroupDatabasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage-groups') ||
            $user->hasPermission('view-groups');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage-groups') ||
            $user->hasPermission('create-groups');
    }

    public function update(User $user): bool
    {
        return $user->hasPermission('manage-groups') ||
            $user->hasPermission('update-groups');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('manage-groups') ||
            $user->hasPermission('delete-groups');
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
