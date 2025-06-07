<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserActivityLog extends Model
{
    protected $casts = [
        'metadata' => 'array',
        'timestamp' => 'datetime'
    ];

    protected $fillable = [
        'user_id',
        'type',
        'description',
        'metadata',
        'timestamp'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
