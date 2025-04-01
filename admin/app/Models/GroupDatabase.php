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

    public static function databaseGroups(int $userId, int $teamId)
    {
        $groups = self::where('user_id', $userId)->where('team_id', $teamId)
            ->orWhereHas('team.members', fn($q) => $q->where('user_id', $userId))
            ->withCount('members')
            ->with([
                'user:id,name',
                'team:id,name',
                'members' => fn($q) => $q->with('tokens')
            ])
            ->get()
            ->map(fn($group) => [
                'id' => $group->id,
                'name' => $group->name,
                'user' => $group->user->only('id', 'name'),
                'team' => $group->team->only('id', 'name'),
                'members_count' => $group->members_count,
                'members' => $group->members->map(fn($m) => [
                    'id' => $m->id,
                    'database_name' => $m->database_name,
                    'is_schema' => $m->is_schema,
                    'tokens' => $m->tokens
                ]),
                'group_token' => $group->tokens()->where('group_id', $group->id)->first(),
                'has_token' => ($group->tokens->count() + self::countMemberTokens()) > 0
            ]);

        return $groups->collect()->filter(function ($group) use ($userId, $teamId) {
            return $group['user']['id'] == $userId && $group['team']['id'] == $teamId;
        })->sortBy('members_count', SORT_REGULAR, true)->values();
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
