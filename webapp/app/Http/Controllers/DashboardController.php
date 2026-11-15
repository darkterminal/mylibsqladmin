<?php

namespace App\Http\Controllers;

use App\Models\QueryMetric;
use App\Models\UserDatabase;
use App\Services\SqldService;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $databases = session('team_databases')['databases'] ?? SqldService::getDatabases(config('mylibsqladmin.local_instance'));

        // Deferred props: the expensive queries run only when the client
        // actually requests them (partial reload), not on every page hit.
        return Inertia::render('dashboard', [
            'databases' => $databases,
            'databaseMetrics' => Inertia::defer(fn() => QueryMetric::summarized()),
            'mostUsedDatabases' => Inertia::defer(fn() => UserDatabase::mostUsedDatabases())
        ]);
    }
}
