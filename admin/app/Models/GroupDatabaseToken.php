<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class GroupDatabaseToken extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'group_id',
        'name',
        'full_access_token',
        'read_only_token',
        'expiration_day'
    ];

    public function group()
    {
        return $this->belongsTo(GroupDatabase::class, 'group_id');
    }
}
