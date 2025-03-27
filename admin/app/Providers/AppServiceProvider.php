<?php

namespace App\Providers;

use App\Models\User;
use App\Models\UserDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        date_default_timezone_set(config('app.timezone'));

        // Global before hook for Super Admin
        Gate::before(function (User $user) {
            if ($user->hasRole('Super Admin')) {
                return true;
            }
        });

        // Team-related Gates
        Gate::define('create-team', function (User $user) {
            return $user->hasPermission('create-teams');
        });

        // Database token Gates
        Gate::define('create-database-token', function (User $user, UserDatabase $database) {
            return $user->ownsDatabase($database) ||
                $user->teams()->whereHas('groups.databases', function ($query) use ($database) {
                    $query->where('id', $database->id);
                })->exists();
        });
    }
}
