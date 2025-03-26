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
        return $user->can('manage-database-tokens') ||
            $user->can('manage-group-databases');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, UserDatabase $userDatabase): bool
    {
        return $user->id === $userDatabase->user_id ||
            $user->can('manage-group-databases');
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->can('manage-database-tokens');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, UserDatabase $userDatabase): bool
    {
        return $user->id === $userDatabase->user_id ||
            $user->can('manage-group-databases');
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, UserDatabase $userDatabase): bool
    {
        return $user->id === $userDatabase->user_id ||
            $user->can('manage-group-databases');
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, UserDatabase $userDatabase): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, UserDatabase $userDatabase): bool
    {
        return false;
    }
}
