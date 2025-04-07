<?php

namespace App\Policies;

use App\Models\User;
use App\Models\GroupDatabaseToken;

class GroupDatabaseTokenPolicy
{
    public function viewAny(User $user): bool
    {
        // Allow viewing if user has team access and proper permissions
        return $user->hasRole('Super Admin') ||
            ($user->teams()->exists() &&
                $user->can('manage-group-database-tokens'));
    }

    public function view(User $user, GroupDatabaseToken $token): bool
    {
        // Check team access through the token's group
        return $user->hasRole('Super Admin') ||
            ($user->hasTeamAccess($token->group->team_id) &&
                $user->can('manage-group-database-tokens'));
    }

    public function create(User $user): bool
    {
        // Additional check for active team membership
        return $user->hasRole('Super Admin') ||
            $user->can('manage-group-database-tokens');
    }

    public function update(User $user, GroupDatabaseToken $token): bool
    {
        // Include expiration check for tokens
        return $user->hasRole('Super Admin') ||
            (!$token->isExpired() &&
                $user->hasTeamAccess($token->group->team_id) &&
                $user->can('manage-group-database-tokens'));
    }

    public function delete(User $user, GroupDatabaseToken $token): bool
    {
        // Prevent deletion of active tokens for non-admins
        return $user->hasRole('Super Admin') ||
            ($token->isExpired() &&
                $user->hasTeamAccess($token->group->team_id) &&
                $user->can('manage-group-database-tokens'));
    }
}
