<?php

namespace App\Policies;

use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage-roles') ||
            $user->hasPermission('view-roles');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage-roles') ||
            $user->hasPermission('create-roles');
    }

    public function update(User $user): bool
    {
        return $user->hasPermission('manage-roles') ||
            $user->hasPermission('update-roles');
    }

    public function delete(User $user): bool
    {
        return $user->hasPermission('manage-roles') ||
            $user->hasPermission('delete-roles');
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
