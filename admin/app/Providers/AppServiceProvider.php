<?php

namespace App\Providers;

use App\Models\User;
use App\Models\Team;
use App\Models\UserDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        date_default_timezone_set(config('app.timezone'));

        Route::bind('team', function ($value) {
            return Team::with(['members', 'groups'])
                ->findOrFail($value);
        });

        // Global Super Admin bypass
        Gate::before(function (User $user) {
            if ($user->hasRole('Super Admin')) {
                return true;
            }
        });

        // Team Authorization Gates
        Gate::define('manage-team', function (User $user, Team $team) {
            return $team->isAdmin($user->id) &&
                $user->hasPermission('manage-teams');
        });

        // Database Token Gates
        Gate::define('manage-database-tokens', function (User $user, UserDatabase $database) {
            return $user->ownsDatabase($database) ||
                $user->teams()->whereHas('groups.databases', function ($query) use ($database) {
                    $query->where('id', $database->id)
                        ->where('permission_level', '<=', 3); // database-maintener or higher
                })->exists();
        });
    }
}
