<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Auth\Access\Response;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage-users') ||
            $user->hasPermission('view-users');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage-users') ||
            $user->hasPermission('create-users');
    }

    public function update(User $user): bool
    {
        return $user->hasPermission('manage-users') ||
            $user->hasPermission('update-users');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('manage-users') ||
            $user->hasPermission('delete-users');
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
