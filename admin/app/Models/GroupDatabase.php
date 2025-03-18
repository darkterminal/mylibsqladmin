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

    public function group_tokens()
    {
        return $this->hasMany(GroupDatabaseToken::class, 'group_id');
    }
}
