<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Team extends Model
{
    use SoftDeletes;

    protected $fillable = ['name', 'description'];

    public function members()
    {
        return $this->belongsToMany(User::class)
            ->withPivot('permission_level')
            ->withTimestamps();
    }

    public function groups()
    {
        return $this->hasMany(GroupDatabase::class);
    }

    public function hasAccess(User $user, string $requiredLevel)
    {
        $levels = ['member' => 1, 'maintainer' => 2, 'admin' => 3];

        return $user->hasRole('Super Admin') ||
            ($this->members->contains($user) &&
                $levels[$this->members->find($user->id)->pivot->permission_level] >= $levels[$requiredLevel]);
    }

    public function scopedGroups(User $user)
    {
        if ($user->hasRole('Super Admin')) {
            return $this->groups;
        }

        return $this->groups()->whereHas('team', function ($q) use ($user) {
            $q->whereHas('members', function ($q) use ($user) {
                $q->where('user_id', $user->id);
            });
        });
    }
}
