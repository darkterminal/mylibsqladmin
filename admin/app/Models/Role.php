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

    public static function superAdmin(): Role
    {
        return static::firstOrCreate([
            'name' => 'Super Admin',
            'description' => 'Full system access'
        ]);
    }

    public static function teamManager(): Role
    {
        return static::firstOrCreate([
            'name' => 'Team Manager',
            'description' => 'Manages team resources'
        ]);
    }
}
