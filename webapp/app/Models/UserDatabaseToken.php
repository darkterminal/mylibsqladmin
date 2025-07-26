<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class UserDatabaseToken extends Model
{
    use SoftDeletes;

    protected $table = 'user_database_tokens';

    protected $fillable = [
        'user_id',
        'database_id',
        'name',
        'full_access_token',
        'read_only_token',
        'expiration_day',
        'created_by',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function database()
    {
        return $this->belongsTo(UserDatabase::class);
    }

    public static function getTokenByDatabaseName(
        string $databaseName,
        string $type = 'full_access_token',
        int|string|null $userIdentifier = null
    ): ?string {
        $query = self::whereHas('database', function ($query) use ($databaseName) {
            $query->where('database_name', $databaseName);
        });

        if ($userIdentifier) {
            if (is_numeric($userIdentifier)) {
                $query->where('user_id', (int) $userIdentifier);
            } else {
                $query->whereHas('user', function ($q) use ($userIdentifier) {
                    $q->where('username', $userIdentifier);
                });
            }
        }

        return $query->latest()->value($type) ?: null;
    }
}
