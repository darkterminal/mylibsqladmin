<?php

namespace App\Policies;

use App\Models\GroupDatabase;
use App\Models\User;

class GroupDatabasePolicy
{
    public function viewAny(User $user)
    {
        return $user->hasPermission('manage-group-databases');
    }

    public function view(User $user, GroupDatabase $group)
    {
        return $group->team->hasMember($user->id) &&
            $user->hasPermission('access-team-databases');
    }

    public function create(User $user)
    {
        return $user->hasPermission('manage-group-databases');
    }

    public function update(User $user, GroupDatabase $group)
    {
        return $group->team->isMaintainer($user->id) &&
            $user->hasPermission('manage-group-databases');
    }

    public function delete(User $user, GroupDatabase $group)
    {
        return $group->team->isAdmin($user->id) &&
            $user->hasPermission('manage-group-databases');
    }

    public function manageTokens(User $user, GroupDatabase $group)
    {
        return $group->team->isMaintainer($user->id) &&
            $user->hasPermission('manage-group-database-tokens');
    }
}
