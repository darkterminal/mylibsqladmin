<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    use HasFactory, Notifiable, SoftDeletes;

    protected $fillable = [
        'name',
        'username',
        'email',
        'password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function databases(): HasMany
    {
        return $this->hasMany(UserDatabase::class);
    }

    public function databaseTokens(): HasMany
    {
        return $this->hasMany(UserDatabaseToken::class);
    }

    public function groupDatabases(): HasMany
    {
        return $this->hasMany(GroupDatabase::class);
    }

    public function permissions()
    {
        return $this->roles->flatMap->permissions->pluck('name')->unique();
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)->withTimestamps();
    }

    public function hasRole($role): bool
    {
        if (is_string($role)) {
            return $this->roles->contains('name', $role);
        }

        if ($role instanceof Role) {
            return $this->roles->contains('id', $role->id);
        }

        return false;
    }

    public function hasPermission($permission): bool
    {
        if (is_string($permission)) {
            return $this->roles->flatMap(fn($role) => $role->permissions)->contains('name', $permission);
        }

        return false;
    }

    public function assignRole($role): void
    {
        $this->roles()->syncWithoutDetaching(
            Role::where('name', $role)->firstOrFail()
        );
    }

    public function getPermissionsAttribute()
    {
        return $this->roles->load('permissions')->flatMap->permissions->pluck('name')->unique();
    }
}
