<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        date_default_timezone_set(config('app.timezone'));

        Inertia::share([
            'configs' => [
                'sqldHost' => config('mylibsqladmin.libsql.connection.host'),
                'sqldPort' => config('mylibsqladmin.libsql.connection.port'),
            ]
        ]);
    }
}
