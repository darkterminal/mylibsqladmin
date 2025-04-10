<?php

namespace App\Http\Controllers;

use App\Models\GroupDatabase;
use App\Models\GroupDatabaseToken;
use App\Models\QueryMetric;
use App\Models\Team;
use App\Models\TopQuery;
use App\Models\UserDatabase;
use App\Models\UserDatabaseToken;
use App\Services\DatabaseTokenGenerator;
use App\Services\SqldService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $databases = session('team_databases')['databases'] ?? SqldService::getDatabases();
        $mostUsedDatabases = UserDatabase::mostUsedDatabases();
        $databaseMetrics = QueryMetric::summarized();

        return Inertia::render('dashboard', [
            'databases' => $databases,
            'databaseMetrics' => Inertia::defer(fn() => $databaseMetrics),
            'mostUsedDatabases' => Inertia::defer(fn() => $mostUsedDatabases)
        ]);
    }

    public function indexToken()
    {
        $userId = auth()->id();
        $teamId = session('team_databases.team_id');

        // Get tokenized database IDs in a single query (team-filtered)
        $tokenizedDatabaseIds = UserDatabaseToken::where('user_id', $userId)
            ->whereHas('database.groups.team', fn($q) => $q->where('id', $teamId))
            ->pluck('database_id')
            ->toArray();

        // Get most used databases with token status (team-filtered)
        $mostUsedDatabases = UserDatabase::mostUsedDatabases();
        $databases = collect($mostUsedDatabases)->map(function ($database) use ($tokenizedDatabaseIds, $teamId) {
            // Filter groups to current team
            $databaseGroups = collect($database['groups'] ?? [])->filter(fn($g) => ($g['team_id'] ?? null) == $teamId);

            return [
                ...$database,
                'groups' => $databaseGroups,
                'database_name' => "{$database['database_name']} - (" .
                    (in_array($database['database_id'], $tokenizedDatabaseIds) ? 'tokenized' : 'not-tokenized') . ")",
                'is_tokenized' => in_array($database['database_id'], $tokenizedDatabaseIds)
            ];
        });

        // Main tokens query with team filtering
        $userDatabaseTokens = UserDatabaseToken::with([
            'database.groups' => fn($q) => $q->whereHas('team', fn($q) => $q->where('id', $teamId)),
            'user:id,name'
        ])
            ->whereHas('database.groups.team', fn($q) => $q->where('id', $teamId))
            ->when(!auth()->user()->hasRole('Super Admin'), fn($q) => $q->where('user_id', $userId))
            ->paginate(10)
            ->through(function ($token) {
                $expirationDate = now()->addDays($token->expiration_day);

                // Get team from filtered groups
                $team = $token->database->groups->first()?->team;

                return [
                    ...$token->toArray(),
                    'expiration_day' => now()->isAfter($expirationDate)
                        ? "Expired"
                        : $expirationDate->format('Y-m-d'),
                    'groups' => $token->database->groups->map(fn($group) => [
                        'id' => $group->id,
                        'name' => $group->name,
                        'team' => $team ? [
                            'id' => $team->id,
                            'name' => $team->name
                        ] : null
                    ]),
                    'team' => $team
                ];
            });

        return Inertia::render('dashboard-token', [
            'mostUsedDatabases' => $databases,
            'isAllTokenized' => $databases->every('is_tokenized'),
            'userDatabaseTokens' => $userDatabaseTokens
        ]);
    }

    private function determineDatabaseType($isSchema)
    {
        if (is_numeric($isSchema) && (int) $isSchema === 1) {
            return 'schema';
        }

        if (is_numeric($isSchema) && (int) $isSchema === 0) {
            return 'standalone';
        }

        return 'child';
    }
}
