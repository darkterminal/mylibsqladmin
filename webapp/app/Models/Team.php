<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'description'];

    protected static function booted()
    {
        static::deleting(function ($team) {
            if ($team->isForceDeleting()) {
                // Permanent deletion logic
                $team->members()->detach();
                $team->invitations()->forceDelete();
                $team->groups()->forceDelete();
                UserDatabase::where('team_id', $team->id)->forceDelete();
                $team->recentActivities()->forceDelete();
            } else {
                // Soft deletion logic
                $team->invitations()->delete();
                $team->groups()->delete();
                UserDatabase::where('team_id', $team->id)->delete();
                $team->recentActivities()->delete();
            }
        });

        static::restoring(function ($team) {
            // Use direct model queries for restoration
            Invitation::onlyTrashed()->where('team_id', $team->id)->restore();
            GroupDatabase::onlyTrashed()->where('team_id', $team->id)->restore();
            UserDatabase::onlyTrashed()->where('team_id', $team->id)->restore();
            ActivityLog::onlyTrashed()->where('team_id', $team->id)->restore();
        });
    }

    public function members()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('permission_level')
            ->withTimestamps();
    }

    public function hasMember(int $userId)
    {
        return $this->members()->where('users.id', $userId)->exists();
    }

    public function invitations()
    {
        return $this->hasMany(Invitation::class)->withoutTrashed()->with('inviter');
    }

    public function groups()
    {
        return $this->hasMany(GroupDatabase::class);
    }

    public function getAllGroupsAttribute()
    {
        return $this->groups()->with(['members', 'tokens'])->get();
    }

    public function getPermissionLevel(User $user)
    {
        $member = $this->members()->find($user->id);
        return $member ? $member->pivot->permission_level : null;
    }

    public function hasAccess(User $user, array $requiredLevels)
    {
        if ($user->hasRole('Super Admin'))
            return true;

        $userLevel = $this->getPermissionLevel($user);
        if (!$userLevel)
            return false;

        $levels = [
            'super-admin' => 1,
            'team-manager' => 2,
            'database-maintainer' => 3,
            'member' => 4
        ];

        $requiredLevels = array_map(fn($l) => $levels[$l], $requiredLevels);
        $minRequired = min($requiredLevels);

        return $levels[$userLevel] <= $minRequired;
    }

    public function isSuperAdmin(User $user)
    {
        return $this->getPermissionLevel($user) === 'super-admin';
    }

    public static function setTeamDatabases(int $userId, int|string $teamId)
    {
        if ($teamId === 'null') {
            $team = Team::whereHas('members', fn($q) => $q->where('user_id', $userId))->latest()->first();
            $teamId = $team->id;
        }

        $team = Team::with(['groups.members.user'])
            ->findOrFail($teamId);

        $team->touch();

        $user = User::find($userId);
        $hasManageDatabasePermisson = $user->hasRole('Super Admin') || $user->hasPermission('manage-teams');

        // Format data
        $userGroups = GroupDatabase::where('team_id', $teamId)
            ->whereHas('members', function ($q) use ($userId) {
                $q->whereHas('tokens', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                });
            })->get();

        $databases = $hasManageDatabasePermisson ? $team->groups->flatMap(fn($group) => $group->members->map(fn($member) => [
            'id' => $member->id,
            'user_id' => $member->user_id,
            'database_name' => $member->database_name,
            'is_schema' => $member->is_schema,
        ])) : $userGroups->flatMap(fn($group) => $group->members->map(fn($member) => [
                        'id' => $member->id,
                        'user_id' => $member->user_id,
                        'database_name' => $member->database_name,
                        'is_schema' => $member->is_schema,
                    ]));

        // Store in session
        session([
            'team_databases' => [
                'team_id' => $teamId,
                'databases' => $databases,
                'groups' => GroupDatabase::databaseGroups($userId, $teamId),
                'expires_at' => now()->addHours(2)
            ]
        ]);
    }

    public function recentActivities()
    {
        return $this->hasMany(ActivityLog::class)
            ->with(['user', 'database'])
            ->latest()
            ->limit(10);
    }

    public function getRecentActivityAttribute()
    {
        return $this->recentActivities->map(fn($activity) => [
            'id' => $activity->id,
            'user' => $activity->user->name,
            'action' => $activity->action,
            'database' => $activity->database->database_name,
            'time' => $activity->time_ago
        ]);
    }
}
