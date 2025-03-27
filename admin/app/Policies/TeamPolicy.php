<?php

namespace App\Policies;

use App\Models\Team;
use App\Models\User;

class TeamPolicy
{
    public function viewAny(User $user)
    {
        return $user->hasPermission('manage-teams');
    }

    public function view(User $user, Team $team)
    {
        return $team->hasMember($user->id) ||
            $user->hasPermission('manage-teams');
    }

    public function create(User $user)
    {
        return $user->hasPermission('create-teams');
    }

    public function update(User $user, Team $team)
    {
        return $team->isAdmin($user->id) &&
            $user->hasPermission('manage-teams');
    }

    public function delete(User $user, Team $team)
    {
        return $team->isAdmin($user->id) &&
            $user->hasPermission('manage-teams');
    }

    public function manageMembers(User $user, Team $team)
    {
        return $team->isAdmin($user->id) &&
            $user->hasPermission('manage-teams');
    }
}
