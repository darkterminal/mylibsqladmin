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
        'role'
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

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withTimestamps()
            ->withPivot('created_at');
    }

    public function hasRole(string|array $roles): bool
    {
        if (is_string($roles)) {
            return $this->roles->contains('name', $roles);
        }

        return $this->roles->whereIn('name', $roles)->isNotEmpty();
    }

    public function hasPermission(string $permission): bool
    {
        return $this->roles()
            ->whereHas('permissions', fn($query) => $query->where('name', $permission))
            ->exists();
    }

    public function getAllPermissions(): array
    {
        return $this->loadMissing('roles.permissions')
            ->roles
            ->flatMap(fn($role) => $role->permissions)
            ->pluck('name')
            ->unique()
            ->toArray();
    }

    public function assignRole(string $roleName): void
    {
        $role = Role::where('name', $roleName)->firstOrFail();
        $this->roles()->syncWithoutDetaching($role);
    }

    public function getRoleAttribute()
    {
        return $this->roles->first()?->name;
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

    public function ownsDatabase(UserDatabase $database)
    {
        return $this->id === $database->user_id;
    }

    public function hasTeamAccess(int $teamId)
    {
        return $this->teams()->where('team_id', $teamId)->exists();
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
