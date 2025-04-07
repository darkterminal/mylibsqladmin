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
        'name'
    ];

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
        $query = self::withCount('members')
            ->with([
                'user:id,name',
                'members' => function ($query) {
                    $query->with('tokens');
                }
            ]);

        $user = User::find($userId);

        // Super Admin sees all groups
        if ($user->hasRole('Super Admin')) {
            // No additional filters
        }
        // Filter by team if specified
        elseif ($teamId) {
            $query->whereHas('members.groups.team', function ($q) use ($teamId) {
                $q->where('id', $teamId);
            });
        }
        // Otherwise, show only user's own groups
        else {
            $query->where('user_id', $userId);
        }

        return $query->latest()
            ->get()
            ->map(fn($group) => [
                'id' => $group->id,
                'name' => $group->name,
                'members_count' => $group->members_count,
                'user' => $group->user,
                'members' => $group->members->map(fn($m) => [
                    'id' => $m->id,
                    'database_name' => $m->database_name,
                    'is_schema' => $m->is_schema
                ]),
                'database_tokens' => $group->members->flatMap(
                    fn($member) =>
                    $member->tokens->map(fn($token) => [
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
                'can_manage' => $user->hasRole('Super Admin') ||
                    $user->can('manage-group-databases') ||
                    $group->user_id === $userId,
                'can_manage_tokens' => $user->hasRole('Super Admin') ||
                    $user->can('manage-group-database-tokens') ||
                    $group->user_id === $userId
            ]);
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
