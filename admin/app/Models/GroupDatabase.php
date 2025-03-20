<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class GroupDatabase extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
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
        $groupExists = self::where('id', $groupId)
            ->whereHas('members', function ($query) use ($databaseName) {
                $query->where('database_name', $databaseName);
            })->exists();

        if (!$groupExists) {
            return null;
        }

        return self::with(['members', 'tokens'])->find($groupId);
    }

    public static function databaseGroups(int $userId)
    {
        return self::withCount('members')
            ->with([
                'user:id,name',
                'members' => function ($query) {
                    $query->with('tokens');
                }
            ])
            ->where('user_id', $userId)
            ->latest()
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
                'group_token' => $group->tokens()->first()
            ]);
    }
}
