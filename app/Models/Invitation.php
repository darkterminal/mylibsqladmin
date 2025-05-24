<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Invitation extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'token',
        'team_id',
        'inviter_id',
        'permission_level',
        'expires_at',
        'created_by'
    ];

    protected $casts = [
        'expires_at' => 'datetime',
    ];

    public function team()
    {
        return $this->belongsTo(Team::class);
    }

    public function inviter()
    {
        return $this->belongsTo(User::class, 'inviter_id');
    }
}
