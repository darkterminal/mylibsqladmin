<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserDatabase;

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

    public function update(User $user, UserDatabase $database): bool
    {
        return $this->ownerOrSuperAdmin($user, $database)
            && ($user->hasPermission('manage-databases') ||
                $user->hasPermission('update-databases'));
    }

    public function delete(User $user, UserDatabase $database): bool
    {
        return $this->ownerOrSuperAdmin($user, $database)
            && ($user->hasPermission('manage-databases') ||
                $user->hasPermission('delete-databases'));
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, UserDatabase $database): bool
    {
        return $this->ownerOrSuperAdmin($user, $database)
            && $user->hasRole('Super Admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, UserDatabase $database): bool
    {
        return $this->ownerOrSuperAdmin($user, $database)
            && $user->hasRole('Super Admin');
    }

    private function ownerOrSuperAdmin(User $user, UserDatabase $database): bool
    {
        return $user->hasRole('Super Admin') || (int) $database->user_id === (int) $user->id;
    }
}
