<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GroupDatabase extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'user_id',
        'team_id',
        'name',
        'created_by'
    ];

    protected static function booted()
    {
        static::deleting(function ($group) {
            if ($group->isForceDeleting()) {
                $group->tokens()->forceDelete();
                $group->members()->detach();
            } else {
                $group->tokens()->delete();
            }
        });

        // Add this restoring handler
        static::restoring(function ($group) {
            GroupDatabaseToken::onlyTrashed()
                ->where('group_id', $group->id)
                ->restore();
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function members()
    {
        return $this->belongsToMany(UserDatabase::class, 'group_database_members', 'group_id', 'database_id');
    }

    public function tokens()
    {
        return $this->hasMany(GroupDatabaseToken::class, 'group_id');
    }

    public static function getGroupDatabasesIfContains(int $groupId, string $databaseName)
    {
        return self::whereHas('members', fn($q) => $q->where('database_name', $databaseName))
            ->with(['members', 'tokens', 'team'])
            ->find($groupId);
    }

    public static function databaseGroups(int $userId, ?int $teamId = null)
    {
        $user = User::find($userId);
        $isCanManage = $user->hasRole('Super Admin') || $user->hasRole('Team Manager');

        $query = self::query();

        // Base query with essential relationships
        $query->with(['user:id,name', 'team:id,name']);

        // For Super Admin: Show all groups in the specified team and load all members
        if ($isCanManage) {
            if ($teamId) {
                $query->where('team_id', $teamId);
            }
            // Super Admin sees all members, so we load them all and their tokens
            $query->with([
                'members' => function ($query) {
                    $query->with(['tokens', 'grant']);
                }
            ]);
        }
        // For other roles: Show groups where the user is a team member and has granted access to at least one database
        else {
            $query->where('team_id', $teamId)
                ->whereHas('team.members', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })
                // Filter groups to only those containing databases granted to the user
                ->whereHas('members.grant', function ($q) use ($userId) {
                    $q->where('user_id', $userId);
                })
                // Eager load ONLY the members (databases) that are granted to this specific user
                ->with([
                    'members' => function ($query) use ($userId) {
                        $query->whereHas('grant', function ($q) use ($userId) {
                            $q->where('user_id', $userId);
                        })->with(['tokens', 'grant']); // Also load tokens for the granted databases
                    }
                ]);
        }

        // Execute the query
        $groups = $query->orderBy('created_at', 'desc')->get();

        // For Super Admins, we still need to get the total count of all members in the group.
        if ($isCanManage) {
            $groups->loadCount('members');
        }

        return $groups->map(function ($group) use ($user, $isCanManage, $userId) {
            // For non-Super Admins, the count is the size of the filtered 'members' collection.
            // For Super Admins, the count is from the 'members_count' attribute loaded via loadCount.
            $membersCount = $isCanManage ? $group->members_count : $group->members->count();

            return [
                'id' => $group->id,
                'name' => $group->name,
                'members_count' => $membersCount,
                'user' => $group->user,
                'team' => $group->team,
                'members' => $group->members->map(fn($m) => [
                    'id' => $m->id,
                    'database_name' => $m->database_name,
                    'is_schema' => $m->is_schema
                ]),
                'database_tokens' => $group->members->flatMap(
                    fn($member) => $member->tokens->map(fn($token) => [
                        'id' => $token->id,
                        'name' => $token->name,
                        'full_access_token' => $token->full_access_token,
                        'read_only_token' => $token->read_only_token,
                        'expiration_day' => $token->expiration_day,
                        'database_id' => $token->database_id,
                        'user_id' => $token->user_id,
                    ])
                ),
                'has_token' => $group->tokens()->exists(),
                'group_token' => $group->tokens()->first(),
                'can_manage' => $isCanManage ||
                    $user->can('manage-groups') ||
                    $group->user_id === $userId,
                'can_manage_tokens' => $isCanManage ||
                    $user->can('manage-tokens') ||
                    $group->user_id === $userId
            ];
        })->values();
    }

    private static function countMemberTokens()
    {
        return self::withCount('members')
            ->with('tokens')
            ->get()
            ->map(fn($group) => $group->tokens->count())
            ->sum();
    }
}
