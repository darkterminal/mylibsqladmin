<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Role extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'description'
    ];

    /** Canonical role keys accepted by every team-member validator. */
    public const KEYS = [
        'super-admin',
        'team-manager',
        'database-maintainer',
        'member',
    ];

    public static function getRoleName(string $name): string
    {
        $roles = [
            'super-admin' => 'Super Admin',
            'team-manager' => 'Team Manager',
            'database-maintainer' => 'Database Maintainer',
            'member' => 'Member'
        ];

        return $roles[$name] ?? $name;
    }

    public static function getRoleKey(string $displayName): string
    {
        $roles = [
            'Super Admin' => 'super-admin',
            'Team Manager' => 'team-manager',
            'Database Maintainer' => 'database-maintainer',
            'Member' => 'member'
        ];

        return $roles[$displayName] ?? $displayName;
    }


    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)
            ->withTimestamps();
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class)
            ->withTimestamps();
    }


}
