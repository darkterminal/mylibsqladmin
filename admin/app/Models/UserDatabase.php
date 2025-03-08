<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserDatabase extends Model
{
    protected $fillable = [
        'user_id',
        'database_name',
        'is_schema',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
