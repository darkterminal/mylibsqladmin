<?php

namespace App\Http\Controllers;

use App\Models\QueryMetric;
use App\Models\TopQuery;
use App\Models\UserDatabase;
use App\Models\UserDatabaseToken;
use App\Services\DatabaseTokenGenerator;
use App\Services\SqldService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $databases = SqldService::getDatabases();
        $metricts = QueryMetric::scopeLast24Hours();
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
                'top_queries' => $metric->topQueries()->orderBy('rows_read', 'desc')->get()->map(fn($query) => ([
                    'rows_written' => $query->rows_written,
                    'rows_read' => $query->rows_read,
                    'query' => $query->query
                ])),
                'slowest_queries' => $metric->slowestQueries()->orderBy('elapsed_ms', 'desc')->get()->map(fn($query) => ([
                    'rows_written' => $query->rows_written,
                    'rows_read' => $query->rows_read,
                    'query' => $query->query,
                    'elapsed_ms' => $query->elapsed_ms
                ])),
                'created_at' => Carbon::parse($metric->created_at)->setTimezone(env('APP_TIMEZONE', 'UTC'))->format('H:i:s')
            ];
        })
            ->sortByDesc('created_at')
            ->unique(fn($item) => implode('|', [
                $item['rows_read_count'],
                $item['rows_written_count'],
                $item['query_count'],
                $item['storage_bytes_used']
            ]))
            ->values()
            ->toArray();

        return Inertia::render('dashboard', [
            'databases' => $databases,
            'databaseMetrics' => $databaseMetrics,
            'mostUsedDatabases' => $mostUsedDatabases
        ]);
    }

    public function indexToken()
    {
        $mostUsedDatabases = UserDatabase::mostUsedDatabases();
        $databases = collect($mostUsedDatabases)->map(function ($database) {
            $databaseToken = UserDatabaseToken::where('database_id', $database['database_id']);
            $alreadyHasToken = $databaseToken->exists() ? 'tokenized' : 'not-tokenized';
            return [
                ...$database,
                'database_name' => $database['database_name'] . ' - (' . $alreadyHasToken . ')',
                'is_tokenized' => $databaseToken->exists()
            ];
        });
        $userDatabaseTokens = UserDatabaseToken::with(['database'])->where('user_id', auth()->user()->id)->get();

        return Inertia::render('dashboard-token', [
            'mostUsedDatabases' => $databases,
            'isAllTokenized' => collect($databases)->every(fn($database) => $database['is_tokenized']),
            'userDatabaseTokens' => collect($userDatabaseTokens)->map(fn($token) => [
                ...$token->toArray(),
                'expiration_day' => Carbon::parse(Carbon::now())->addDays($token->expiration_day)->format('Y-m-d')
            ])
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

    public function createToken(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'databaseId' => 'required|integer',
            'expiration' => 'required|integer',
        ]);

        $tokenGenerator = (new DatabaseTokenGenerator())->generateToken(
            $validated['databaseId'],
            auth()->id(),
            $validated['expiration']
        );

        if (!$tokenGenerator) {
            return redirect()->route('dashboard.tokens')
                ->with('error', 'Failed to generate tokens');
        }

        $formData = [
            'user_id' => auth()->id(),
            'database_id' => $validated['databaseId'],
            'name' => $validated['name'],
            'full_access_token' => $tokenGenerator['full_access_token'],
            'read_only_token' => $tokenGenerator['read_only_token'],
            'expiration_day' => $validated['expiration'],
        ];

        try {
            $save = UserDatabaseToken::updateOrCreate(
                [
                    'database_id' => $validated['databaseId'],
                    'user_id' => auth()->id(),
                ],
                $formData
            );

            return redirect()->route('dashboard.tokens')
                ->with('success', 'Token created/updated successfully');
        } catch (\Exception $e) {
            return redirect()->route('dashboard.tokens')
                ->with('error', 'Failed to save token: ' . $e->getMessage());
        }
    }
}
