<?php

namespace App\Http\Controllers;

use App\Services\SqldService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        return Inertia::render('dashboard');
    }

    public function createDatabase(Request $request)
    {
        SqldService::createDatabase($request->database, $request->isSchema);
        $databases = SqldService::getDatabases();
        return redirect()->route('dashboard')->with('databases', $databases);
    }

    public function deleteDatabase(string $database)
    {
        SqldService::deleteDatabase($database);
        $databases = SqldService::getDatabases();
        return redirect()->route('dashboard')->with('databases', $databases);
    }
}
