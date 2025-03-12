<?php

namespace App\Http\Controllers;

use App\Models\QueryMetric;
use App\Models\TopQuery;
use App\Models\UserDatabase;
use App\Services\SqldService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $databases = SqldService::getDatabases();
        $metricts = QueryMetric::chartData();
        $mostUsedDatabases = UserDatabase::mostUsedDatabases();
        $databaseMetrics = $metricts->collect()->map(function ($metric) {
            $database = UserDatabase::find($metric->database_id);
            return [
                'id' => $metric->id,
                'name' => $database->database_name,
                'rows_read_count' => $metric->rows_read_count,
                'rows_written_count' => $metric->rows_written_count,
                'storage_bytes_used' => $metric->storage_bytes_used,
                'query_count' => $metric->query_count,
                'elapsed_ms' => $metric->elapsed_ms,
                'write_requests_delegated' => $metric->write_requests_delegated,
                'replication_index' => $metric->replication_index,
                'embedded_replica_frames_replicated' => $metric->embedded_replica_frames_replicated,
                'queries' => empty($metric->queries) ? [] : json_decode($metric->queries, true),
                'top_queries' => $metric->topQueries()->get()->map(function ($query) {
                    return [
                        'rows_written' => $query->rows_written,
                        'rows_read' => $query->rows_read,
                        'query' => $query->query
                    ];
                }),
                'slowest_queries' => $metric->slowestQueries()->get()->map(function ($query) {
                    return [
                        'rows_written' => $query->rows_written,
                        'rows_read' => $query->rows_read,
                        'query' => $query->query,
                        'elapsed_ms' => $query->elapsed_ms
                    ];
                }),
            ];
        })->toArray();

        return Inertia::render('dashboard', [
            'databases' => $databases,
            'databaseMetrics' => $databaseMetrics,
            'mostUsedDatabases' => $mostUsedDatabases
        ]);
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
