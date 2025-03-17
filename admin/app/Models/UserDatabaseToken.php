<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDatabaseToken extends Model
{
    protected $table = 'user_database_tokens';

    protected $fillable = [
        'user_id',
        'database_id',
        'name',
        'full_access_token',
        'read_only_token',
        'expiration_day',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function database()
    {
        return $this->belongsTo(UserDatabase::class);
    }
}
