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

        Route::bind('team', fn($value) => Team::with(['members', 'groups'])->findOrFail($value));
    }
}
