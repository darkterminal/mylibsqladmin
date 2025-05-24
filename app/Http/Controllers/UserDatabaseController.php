<?php

namespace App\Http\Controllers;

use App\Models\QueryMetric;
use App\Models\Team;
use App\Models\UserDatabase;
use App\Services\SqldService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserDatabaseController extends Controller
{
    public function index()
    {
        $user = auth()->user();

        $userId = $user->id;

        $query = UserDatabase::with([
            'groups.team:id,name,description',
            'user:id,name',
            'tokens' => fn($q) => $q->where('user_id', $userId)
        ])->withCount(['queryMetrics as total_queries'])
            ->withSum('queryMetrics as rows_read', 'rows_read_count')
            ->withSum('queryMetrics as rows_written', 'rows_written_count')
            ->withSum('queryMetrics as storage_bytes', 'storage_bytes_used');

        $teamId = session('team_databases.team_id');
        if ($teamId) {
            $query->whereHas('groups.team', fn($q) => $q->where('id', $teamId));
        }

        if (!auth()->user()->hasRole('Super Admin')) {
            $query->where(function ($q) use ($userId, $user) {
                $q->where('user_id', $userId)
                    ->orWhereHas(
                        'groups.team.members',
                        fn($q) => $q
                            ->where('user_id', $userId)
                            ->when(
                                !$user->hasPermission('access-team-databases'),
                                fn($q) => $q->where('permission_level', '<=', 3)
                            )
                    );
            });
        }

        if ($search = request('search')) {
            $query->where('database_name', 'like', "%{$search}%");
        }

        $databases = $query->paginate(10)->through(fn($database) => [
            'id' => $database->id,
            'name' => $database->database_name,
            'is_schema' => $database->is_schema,
            'owner' => $database->user->name,
            'groups' => $database->groups->map(fn($group) => [
                'id' => $group->id,
                'name' => $group->name,
                'team' => [
                    'id' => $group->team->id,
                    'name' => $group->team->name,
                    'description' => $group->team->description
                ]
            ]),
            'teams' => $database->groups->map(fn($group) => [
                'id' => $group->team->id,
                'name' => $group->team->name,
                'description' => $group->team->description
            ])->unique('id')->values(),
            'tokenized' => $database->tokens->isNotEmpty(),
            'token' => $database->tokens->isEmpty() ? null : $database->tokens->map(fn($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'full_access_token' => $token->full_access_token,
                'read_only_token' => $token->read_only_token,
                'expires_at' => $token->expiration_day ?
                    now()->addDays($token->expiration_day)->format('Y-m-d') :
                    'Never'
            ])->first(),
            'stats' => [
                'rows_reads' => $database->rows_read ?: 0,
                'rows_written' => $database->rows_written ?: 0,
                'queries' => $database->total_queries,
                'storage' => $database->storage_bytes ?: 0
            ]
        ]);

        return Inertia::render('dashboard-database', [
            'listOfDatabases' => $databases
        ]);
    }

    public function archived()
    {
        $user = auth()->user();
        $userId = $user->id;

        $query = UserDatabase::onlyTrashed()->with([
            'groups.team:id,name,description',
            'user:id,name',
            'tokens' => fn($q) => $q->where('user_id', $userId)
        ])->withCount(['queryMetrics as total_queries'])
            ->withSum('queryMetrics as rows_read', 'rows_read_count')
            ->withSum('queryMetrics as rows_written', 'rows_written_count')
            ->withSum('queryMetrics as storage_bytes', 'storage_bytes_used');

        $teamId = session('team_databases.team_id');
        if ($teamId) {
            $query->whereHas('groups.team', fn($q) => $q->where('id', $teamId));
        }

        if (!auth()->user()->hasRole('Super Admin')) {
            $query->where(function ($q) use ($userId, $user) {
                $q->where('user_id', $userId)
                    ->orWhereHas(
                        'groups.team.members',
                        fn($q) => $q
                            ->where('user_id', $userId)
                            ->when(
                                !$user->hasPermission('access-team-databases'),
                                fn($q) => $q->where('permission_level', '<=', 3)
                            )
                    );
            });
        }

        if ($search = request('search')) {
            $query->where('database_name', 'like', "%{$search}%");
        }

        $archivedDatabases = $query->paginate(10)->through(fn($database) => [
            'id' => $database->id,
            'name' => $database->database_name,
            'is_schema' => $database->is_schema,
            'owner' => $database->user->name,
            'deleted_at' => $database->deleted_at->format('Y-m-d H:i:s'),
            'groups' => $database->groups->map(fn($group) => [
                'id' => $group->id,
                'name' => $group->name,
                'team' => [
                    'id' => $group->team->id,
                    'name' => $group->team->name,
                    'description' => $group->team->description
                ]
            ]),
            'teams' => $database->groups->map(fn($group) => [
                'id' => $group->team->id,
                'name' => $group->team->name,
                'description' => $group->team->description
            ])->unique('id')->values(),
            'tokenized' => $database->tokens->isNotEmpty(),
            'token' => $database->tokens->isEmpty() ? null : $database->tokens->map(fn($token) => [
                'id' => $token->id,
                'name' => $token->name,
                'full_access_token' => $token->full_access_token,
                'read_only_token' => $token->read_only_token,
                'expires_at' => $token->expiration_day ?
                    now()->addDays($token->expiration_day)->format('Y-m-d') :
                    'Never'
            ])->first(),
            'stats' => [
                'rows_reads' => $database->rows_read ?: 0,
                'rows_written' => $database->rows_written ?: 0,
                'queries' => $database->total_queries,
                'storage' => $database->storage_bytes ?: 0
            ]
        ]);

        return Inertia::render('dashboard-archived-database', [
            'listOfDatabaseArchives' => $archivedDatabases
        ]);
    }

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

            $databases = session('team_databases')['databases'] ?? SqldService::getDatabases(config('mylibsqladmin.local_instance'));
            $mostUsedDatabases = UserDatabase::mostUsedDatabases();
            $databaseMetrics = QueryMetric::summarized();

            Team::setTeamDatabases(auth()->user()->id, $validated['teamId']);

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
        if (!SqldService::archiveDatabase($database)) {
            return redirect()->back()->with(['error' => 'Database deletion failed']);
        }

        return redirect()->back()->with([
            'databases' => SqldService::getDatabases(config('mylibsqladmin.local_instance')),
            'mostUsedDatabases' => UserDatabase::mostUsedDatabases(),
            'databaseMetrics' => QueryMetric::summarized()
        ]);
    }

    public function restoreDatabase(Request $request)
    {
        $database = $request->input('name');
        if (!SqldService::restoreDatabase($database)) {
            return redirect()->back()->with(['error' => 'Database restore failed']);
        }

        return redirect()->back()->with([
            'databases' => SqldService::getDatabases(config('mylibsqladmin.local_instance')),
            'mostUsedDatabases' => UserDatabase::mostUsedDatabases(),
            'databaseMetrics' => QueryMetric::summarized()
        ]);
    }

    public function forceDeleteDatabase(string $database)
    {
        if (!SqldService::deleteDatabase($database)) {
            return redirect()->back()->with(['error' => 'Database force deletion failed']);
        }

        return redirect()->back()->with([
            'databases' => SqldService::getDatabases(config('mylibsqladmin.local_instance')),
            'mostUsedDatabases' => UserDatabase::mostUsedDatabases(),
            'databaseMetrics' => QueryMetric::summarized()
        ]);
    }
}
