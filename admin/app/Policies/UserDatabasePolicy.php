<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserDatabase;
use Illuminate\Auth\Access\Response;

class UserDatabasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage-databases') ||
            $user->hasPermission('view-databases');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage-databases') ||
            $user->hasPermission('create-databases');
    }

    public function update(User $user): bool
    {
        return $user->hasPermission('manage-databases') ||
            $user->hasPermission('update-databases');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('manage-databases') ||
            $user->hasPermission('delete-databases');
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
