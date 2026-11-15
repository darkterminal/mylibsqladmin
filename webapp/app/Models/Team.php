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


    /**
     * The team_databases session payload, guaranteeing its expires_at is
     * enforced. A payload past its TTL (or missing) is transparently
     * refreshed; fresh payloads cost zero queries.
     */
    public static function currentTeamDatabases(): array
    {
        $payload = session('team_databases');

        if (!is_array($payload) || !self::teamDatabasesPayloadIsFresh($payload)) {
            if (auth()->id()) {
                self::setTeamDatabases((int) auth()->id(), $payload['team_id'] ?? null);
                $payload = session('team_databases');
            }
        }

        return is_array($payload)
            ? $payload
            : ['team_id' => null, 'databases' => [], 'groups' => [], 'expires_at' => now()->addHours(2)];
    }

    private static function teamDatabasesPayloadIsFresh(array $payload): bool
    {
        $expiresAt = $payload['expires_at'] ?? null;

        if ($expiresAt instanceof \DateTimeInterface) {
            return $expiresAt > now();
        }

        if (is_string($expiresAt)) {
            try {
                return \Illuminate\Support\Carbon::parse($expiresAt)->isFuture();
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    public static function setTeamDatabases(int $userId, int|string|null $teamId = null)
    {
        if ($teamId === null || $teamId === 'null' || $teamId === '') {
            $teamId = Team::whereHas('members', fn ($q) => $q->where('user_id', $userId))->latest()->first()?->id;
            if ($teamId === null) {
                // User belongs to no team yet — write an empty payload so
                // callers never explode on a missing membership.
                session([
                    'team_databases' => [
                        'team_id' => null,
                        'databases' => [],
                        'groups' => [],
                        'expires_at' => now()->addHours(2),
                    ],
                ]);

                return;
            }
        }

        $team = Team::with(['groups.members.user'])
            ->findOrFail($teamId);

        $team->touch();

        $user = User::find($userId);
        $hasManageDatabasePermisson = $user->hasRole('Super Admin') || $user->hasPermission('manage-teams');

        // Format data
        $userGroups = GroupDatabase::where('team_id', $teamId)
            ->whereHas('members', function ($q) use ($userId) {
                $q->whereHas('grant', function ($q) use ($userId) {
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

}
