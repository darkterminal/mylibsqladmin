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
        $mostUsedDatabases = UserDatabase::mostUsedDatabases();
        $databaseMetrics = QueryMetric::summarized();

        return Inertia::render('dashboard', [
            'databases' => $databases,
            'databaseMetrics' => Inertia::defer(fn() => $databaseMetrics),
            'mostUsedDatabases' => Inertia::defer(fn() => $mostUsedDatabases)
        ]);
    }
}
