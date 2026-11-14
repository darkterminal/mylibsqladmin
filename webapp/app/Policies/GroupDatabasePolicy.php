<?php

namespace App\Policies;

use App\Models\GroupDatabase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class GroupDatabasePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('manage-groups') ||
            $user->hasPermission('view-groups');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('manage-groups') ||
            $user->hasPermission('create-groups');
    }

    public function update(User $user, GroupDatabase $group): bool
    {
        return $this->sameTeamOrSuperAdmin($user, (int) $group->team_id)
            && ($user->hasPermission('manage-groups') ||
                $user->hasPermission('update-groups'));
    }

    public function delete(User $user, GroupDatabase $group): bool
    {
        return $this->sameTeamOrSuperAdmin($user, (int) $group->team_id)
            && ($user->hasPermission('manage-groups') ||
                $user->hasPermission('delete-groups'));
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

    private function sameTeamOrSuperAdmin(User $user, int $teamId): bool
    {
        if ($user->hasRole('Super Admin')) {
            return true;
        }

        return DB::table('team_user')
            ->where('team_id', $teamId)
            ->where('user_id', $user->id)
            ->exists();
    }
}
