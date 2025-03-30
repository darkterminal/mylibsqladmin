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

    protected $appends = ['permission_names'];

    protected $with = ['teams'];

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

    public function ownsDatabase(UserDatabase $database)
    {
        return $this->id === $database->user_id;
    }

    public function hasTeamAccessToDatabase(UserDatabase $database)
    {
        return $this->teams()
            ->whereHas('groups.databases', function ($query) use ($database) {
                $query->where('id', $database->id);
            })
            ->exists();
    }

    public function isTeamAdmin(Team $team)
    {
        return $this->teams()
            ->where('team_id', $team->id)
            ->where('permission_level', 'admin')
            ->exists();
    }

    public function getPermissionsAttribute()
    {
        return cache()->remember("user-{$this->id}-permissions", 3600, function () {
            return $this->permissions()->pluck('name')->toArray();
        });
    }

    public function getPermissionNamesAttribute()
    {
        return $this->roles()
            ->with('permissions')
            ->get()
            ->flatMap(fn($role) => $role->permissions->pluck('name'))
            ->unique()
            ->values()
            ->toArray();
    }

    public function getAllPermissions()
    {
        return $this->hasPermissionViaRoles();
    }

    protected function hasPermissionViaRoles()
    {
        return $this->loadMissing('roles.permissions')
            ->roles
            ->flatMap(fn($role) => $role->permissions)
            ->pluck('name')
            ->unique();
    }

    public function teams()
    {
        return $this->belongsToMany(Team::class)
            ->withPivot('permission_level')
            ->withTimestamps();
    }
}
