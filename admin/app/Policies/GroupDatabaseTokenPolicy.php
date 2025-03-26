<?php

namespace App\Policies;

use App\Models\GroupDatabaseToken;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class GroupDatabaseTokenPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage-group-database-tokens');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, GroupDatabaseToken $token): bool
    {
        return $user->hasPermission('manage-group-database-tokens') &&
            $this->isGroupManager($user, $token);
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->hasPermission('manage-group-database-tokens');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, GroupDatabaseToken $token): bool
    {
        return $user->hasPermission('manage-group-database-tokens') &&
            $this->isGroupManager($user, $token);
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, GroupDatabaseToken $token): bool
    {
        return $user->hasPermission('manage-group-database-tokens') &&
            $this->isGroupManager($user, $token);
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, GroupDatabaseToken $token): bool
    {
        return $user->hasRole('Super Admin');
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, GroupDatabaseToken $token): bool
    {
        return $user->hasRole('Super Admin');
    }

    /**
     * Check if user is group manager or owner
     */
    protected function isGroupManager(User $user, GroupDatabaseToken $token): bool
    {
        // Super Admins can manage all tokens
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        // Get the associated group through the token's relationship
        $group = $token->group;

        // Check if user is the group owner
        if ($group->user_id === $user->id) {
            return true;
        }

        // Check if user is a Team Manager in the group
        return $group->members()
            ->where('user_id', $user->id)
            ->whereHas('roles', fn($q) => $q->where('name', 'Team Manager'))
            ->exists();
    }
}
