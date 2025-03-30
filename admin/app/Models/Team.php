<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'description'];

    public function members()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('permission_level')
            ->withTimestamps();
    }

    public function groups()
    {
        return $this->hasMany(GroupDatabase::class);
    }

    public function getAllGroupsAttribute()
    {
        return $this->groups()->with(['members', 'tokens'])->get();
    }

    public function hasAccess(User $user, string $requiredLevel)
    {
        $levels = ['member' => 1, 'maintainer' => 2, 'admin' => 3];

        return $user->hasRole('Super Admin') ||
            ($this->members->contains($user) &&
                $levels[$this->getPermissionLevel($user)] >= $levels[$requiredLevel]);
    }

    public function getPermissionLevel(User $user)
    {
        return $this->members->find($user->id)->pivot->permission_level;
    }

    public static function setTeamDatabases(int $userId, int $teamId)
    {
        $team = Team::with(['groups.members.user'])
            ->findOrFail($teamId);

        // Format data
        $databases = $team->groups->flatMap(fn($group) => $group->members->map(fn($member) => [
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
}
