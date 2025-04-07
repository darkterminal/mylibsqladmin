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
        $databaseMetrics = QueryMetric::summariezed();

        return Inertia::render('dashboard', [
            'databases' => $databases,
            'databaseMetrics' => Inertia::defer(fn() => $databaseMetrics),
            'mostUsedDatabases' => Inertia::defer(fn() => $mostUsedDatabases)
        ]);
    }

    public function indexDatabase()
    {
        $userId = auth()->id();

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
            $query->where(function ($q) use ($userId) {
                $q->where('user_id', $userId)
                    ->orWhereHas(
                        'groups.team.members',
                        fn($q) => $q
                            ->where('user_id', $userId)
                            ->when(
                                !auth()->user()->can('access-team-databases'),
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

    public function indexGroup()
    {
        $user = auth()->user();
        $teamId = session('team_databases')['team_id'] ?? null;

        if ($user->hasRole('Super Admin')) {
            $databaseGroups = GroupDatabase::databaseGroups($user->id, $teamId);
        } elseif ($user->hasRole('Team Manager')) {
            $databaseGroups = GroupDatabase::databaseGroups($user->id, $teamId);
        } elseif ($user->can('access-team-databases')) {
            $databaseGroups = GroupDatabase::databaseGroups($user->id, $teamId);
        } else {
            $databaseGroups = GroupDatabase::databaseGroups($user->id, null);
        }

        $databaseNotInGroup = UserDatabase::where('user_id', $user->id)
            ->whereDoesntHave('groups')
            ->get(['id', 'database_name', 'is_schema']);

        return Inertia::render('dashboard-group', [
            'databaseGroups' => $databaseGroups,
            'databaseNotInGroup' => $databaseNotInGroup
        ]);
    }

    public function indexTeam(Request $request)
    {
        $user = auth()->user();

        // Build the base query
        $teamsQuery = Team::with([
            'members',
            'groups.members' => function ($query) {
                $query->with(['latestActivity', 'user'])
                    ->select('id', 'database_name', 'is_schema', 'user_id', 'created_at');
            },
            'recentActivities.user'
        ]);

        // Apply filtering based on roles and permissions
        if ($user->hasRole('Super Admin')) {
            // Super Admin sees all teams
            $teams = $teamsQuery->get();
        } elseif ($user->can('manage-teams')) {
            // Team Managers see teams they're members of
            $teams = $teamsQuery->whereHas('members', fn($q) => $q->where('user_id', $user->id))->get();
        } else {
            // Regular members see only their teams
            $teams = $teamsQuery->whereHas('members', fn($q) => $q->where('user_id', $user->id))->get();
        }

        $teamData = $teams->map(function ($team) use ($user) {
            $isTeamMember = $team->members->contains('id', $user->id);
            $canAccessDatabases = $user->hasRole('Super Admin') ||
                ($user->can('access-team-databases') && $isTeamMember);

            return [
                'id' => $team->id,
                'name' => $team->name,
                'description' => $team->description,
                'members' => $team->members->count(),
                'team_members' => $team->members->map(fn($member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $member->pivot->permission_level,
                ]),
                'groups' => $canAccessDatabases ? $team->groups->map(fn($group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'databases' => $group->members->map(fn($database) => [
                        'id' => $database->id,
                        'name' => $database->database_name,
                        'type' => $this->determineDatabaseType($database->is_schema),
                        'lastActivity' => $database->latestActivity?->created_at->diffForHumans() ?? 'No activity'
                    ])
                ]) : [],
                'recentActivity' => $canAccessDatabases ? $team->recentActivities->map(fn($activity) => [
                    'id' => $activity->id,
                    'user' => $activity->user->name,
                    'action' => $activity->action,
                    'database' => $activity->database->database_name,
                    'time' => $activity->created_at->diffForHumans()
                ]) : []
            ];
        });

        return Inertia::render('dashboard-team', [
            'teams' => $teamData
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
