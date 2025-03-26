<?php

namespace App\Policies;

use App\Models\GroupDatabase;
use App\Models\User;

class GroupDatabasePolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        // Allow viewing if user has group management permission
        return $user->hasPermission('manage-group-databases');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, GroupDatabase $group): bool
    {
        // Allow if owner or has management permission
        return $user->id === $group->user_id ||
            $user->hasPermission('manage-group-databases');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        // Allow creation with group management permission
        return $user->hasPermission('manage-group-databases');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, GroupDatabase $group): bool
    {
        // Allow update if owner or has management permission
        return $user->id === $group->user_id ||
            $user->hasPermission('manage-group-databases');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, GroupDatabase $group): bool
    {
        // Allow deletion if owner or has management permission
        return $user->id === $group->user_id ||
            $user->hasPermission('manage-group-databases');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, GroupDatabase $group): bool
    {
        // Only allow Super Admin to restore
        return $user->hasRole('Super Admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, GroupDatabase $group): bool
    {
        // Only allow Super Admin to force delete
        return $user->hasRole('Super Admin');
    }
}
