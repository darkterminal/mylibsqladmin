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
        'token',
    ];
}
