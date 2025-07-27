<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GrantedDatabase extends Model
{
    use SoftDeletes;

    protected $table = 'granted_user_databases';

    protected $fillable = [
        'user_id',
        'database_id',
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
