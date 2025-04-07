<?php

namespace App\Http\Controllers;

use App\Models\QueryMetric;
use App\Models\Team;
use App\Models\UserDatabase;
use App\Services\SqldService;
use Illuminate\Http\Request;

class DatabaseController extends Controller
{
    public function createDatabase(Request $request)
    {
        try {
            $validated = $request->validate([
                'database' => 'required|string',
                'isSchema' => 'required',
                'groupId' => 'required|integer|exists:group_databases,id',
                'teamId' => 'required|integer|exists:teams,id'
            ]);

            SqldService::createDatabase(
                $validated['database'],
                $validated['isSchema'],
                $validated['groupId'],
                $validated['teamId']
            );

            $databases = session('team_databases')['databases'] ?? SqldService::getDatabases();
            $mostUsedDatabases = UserDatabase::mostUsedDatabases();
            $databaseMetrics = QueryMetric::summariezed();

            Team::setTeamDatabases(auth()->id(), $validated['teamId']);

            return response()->json([
                'success' => true,
                'databases' => $databases,
                'mostUsedDatabases' => $mostUsedDatabases,
                'databaseMetrics' => $databaseMetrics
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
        }
    }

    public function deleteDatabase(string $database)
    {
        SqldService::deleteDatabase($database);

        return redirect()->back()->with([
            'databases' => SqldService::getDatabases(),
            'mostUsedDatabases' => UserDatabase::mostUsedDatabases(),
            'databaseMetrics' => QueryMetric::summariezed()
        ]);
    }
}
