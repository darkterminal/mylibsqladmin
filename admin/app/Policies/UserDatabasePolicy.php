<?php

namespace App\Policies;

use App\Models\User;
use App\Models\UserDatabase;

class UserDatabasePolicy
{
    public function view(User $user, UserDatabase $database)
    {
        return $user->ownsDatabase($database) ||
            $user->hasTeamAccessToDatabase($database);
    }

    public function update(User $user, UserDatabase $database)
    {
        return $user->ownsDatabase($database);
    }

    public function delete(User $user, UserDatabase $database)
    {
        return $user->ownsDatabase($database);
    }

    public function manageTokens(User $user, UserDatabase $database)
    {
        return $user->ownsDatabase($database) &&
            $user->hasPermission('manage-database-tokens');
    }
}
