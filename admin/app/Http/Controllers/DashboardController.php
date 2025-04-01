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
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
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

    public function createDatabase(Request $request)
    {
        Gate::authorize('create', UserDatabase::class);

        SqldService::createDatabase(
            $request->database,
            $request->isSchema,
            $request->groupId,
            $request->teamId
        );

        $databases = session('team_databases')['databases'] ?? SqldService::getDatabases();
        $mostUsedDatabases = UserDatabase::mostUsedDatabases();
        $databaseMetrics = QueryMetric::summariezed();

        return redirect()->route('dashboard')->with([
            'databases' => $databases,
            'databaseMetrics' => $databaseMetrics,
            'mostUsedDatabases' => $mostUsedDatabases
        ]);
    }

    public function deleteDatabase(string $database)
    {
        SqldService::deleteDatabase($database);

        return redirect()->route('dashboard')->with([
            'databases' => SqldService::getDatabases(),
            'mostUsedDatabases' => UserDatabase::mostUsedDatabases(),
            'databaseMetrics' => QueryMetric::summariezed()
        ]);
    }

    public function indexToken()
    {
        $userId = auth()->id();

        $mostUsedDatabases = UserDatabase::mostUsedDatabases();

        $databases = collect($mostUsedDatabases)->map(function ($database) use ($userId) {
            $exists = UserDatabaseToken::where('database_id', $database['database_id'])
                ->where('user_id', $userId)
                ->exists();

            return [
                ...$database,
                'database_name' => "{$database['database_name']} - (" . ($exists ? 'tokenized' : 'not-tokenized') . ")",
                'is_tokenized' => $exists
            ];
        });

        $userDatabaseTokens = UserDatabaseToken::with(['database'])
            ->where('user_id', $userId)
            ->get()
            ->map(function ($token) {
                $expirationDate = Carbon::now()->addDays($token->expiration_day);

                return [
                    ...$token->toArray(),
                    'expiration_day' => Carbon::now()->isAfter($expirationDate)
                        ? "Expired"
                        : $expirationDate->format('Y-m-d')
                ];
            });

        return Inertia::render('dashboard-token', [
            'mostUsedDatabases' => $databases,
            'isAllTokenized' => $databases->every('is_tokenized'),
            'userDatabaseTokens' => $userDatabaseTokens
        ]);
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
            return redirect()->back()
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
            UserDatabaseToken::updateOrCreate(
                [
                    'user_id' => auth()->id(),
                    'database_id' => $validated['databaseId'],
                    'name' => $validated['name'],
                    'full_access_token' => $tokenGenerator['full_access_token'],
                    'read_only_token' => $tokenGenerator['read_only_token'],
                    'expiration_day' => $validated['expiration'],
                ],
                $formData
            );

            return redirect()->back()->with([
                'success' => 'Token created/updated successfully',
                'newToken' => UserDatabaseToken::latest()->first(),
                'databaseGroups' => GroupDatabase::databaseGroups(auth()->id(), session('team_databases')['team_id'] ?? null),
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to save token: ' . $e->getMessage());
        }
    }

    public function deleteToken(int $tokenId)
    {
        UserDatabaseToken::where('id', $tokenId)->delete();
        return redirect()->back()->with([
            'success' => 'Token deleted successfully',
            'userDatabaseTokens' => UserDatabaseToken::where('user_id', auth()->id())->get()
        ]);
    }

    public function indexGroup()
    {

        $databaseGroups = GroupDatabase::databaseGroups(auth()->id(), session('team_databases')['team_id'] ?? null);

        $databaseNotInGroup = UserDatabase::where('user_id', auth()->id())
            ->whereDoesntHave('groups')
            ->get(['id', 'database_name']);

        return Inertia::render('dashboard-group', [
            'databaseGroups' => $databaseGroups,
            'databaseNotInGroup' => $databaseNotInGroup
        ]);
    }

    public function createGroupToken(GroupDatabase $group, Request $request)
    {
        $validated = $request->validate([
            'group_id' => 'required|integer|exists:group_databases,id',
            'name' => 'required|string|max:255',
            'expiration' => 'required|integer|min:1|max:365'
        ]);

        $tokenGenerator = (new DatabaseTokenGenerator())->generateToken(
            $validated['name'],
            $validated['group_id'],
            $validated['expiration'],
            true
        );

        DB::transaction(function () use ($group, $validated, $tokenGenerator) {
            return $group->tokens()->updateOrCreate(
                [
                    'group_id' => $validated['group_id'],
                ],
                [
                    'name' => $validated['name'],
                    'full_access_token' => $tokenGenerator['full_access_token'],
                    'read_only_token' => $tokenGenerator['read_only_token'],
                    'expiration_day' => $validated['expiration']
                ]
            );
        });

        return redirect()->back()->with([
            'success' => 'Group token created successfully'
        ]);
    }

    public function deleteGroupToken($tokenId)
    {
        GroupDatabaseToken::where('id', $tokenId)->delete();
        return redirect()->back()->with([
            'success' => 'Group token deleted successfully'
        ]);
    }

    public function createGroupOnly(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('group_databases')->where(function ($query) use ($request) {
                        return $query->where('team_id', $request->team_id);
                    })
                ],
                'team_id' => [
                    'required',
                    'integer',
                    'exists:teams,id',
                    function ($attribute, $value, $fail) {
                        if (!auth()->user()->teams()->where('team_id', $value)->exists()) {
                            $fail('You are not a member of this team.');
                        }
                    }
                ]
            ]);

            // Authorization check
            $team = Team::findOrFail($validated['team_id']);
            if (!$team->hasAccess(auth()->user(), 'maintainer')) {
                abort(403, 'Unauthorized action');
            }

            $group = DB::transaction(function () use ($validated) {
                $group = GroupDatabase::create([
                    'name' => $validated['name'],
                    'user_id' => auth()->id(),
                    'team_id' => $validated['team_id'],
                ]);

                return $group->load(['team', 'user:id,name'])
                    ->loadCount('members');
            });

            return response()->json([
                'success' => true,
                'message' => 'Group created successfully',
                'group' => $group
            ], 201);

        } catch (ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Group creation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    public function createGroup(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'team_id' => 'required|integer|exists:teams,id',
            'databases' => 'required|array|min:1',
            'databases.*' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) {
                    if (
                        !UserDatabase::where('id', $value)
                            ->where('user_id', auth()->id())
                            ->exists()
                    ) {
                        $fail('Invalid database selected.');
                    }
                },
            ],
        ]);

        DB::transaction(function () use ($validated) {
            $group = GroupDatabase::create([
                'name' => $validated['name'],
                'user_id' => auth()->id(),
                'team_id' => $validated['team_id'],
            ]);

            $group->members()->sync($validated['databases']);

            return $group->load(['members', 'tokens', 'user'])
                ->loadCount('members');
        });

        $databaseGroups = GroupDatabase::databaseGroups(auth()->id(), $validated['team_id']);

        $databaseNotInGroup = UserDatabase::where('user_id', auth()->id())
            ->whereDoesntHave('groups')
            ->get(['id', 'database_name']);

        return redirect()->back()->with([
            'success' => 'Group created successfully',
            'databaseGroups' => $databaseGroups,
            'databaseNotInGroup' => $databaseNotInGroup
        ]);
    }

    public function deleteGroup($groupId)
    {
        $group = GroupDatabase::with(['members', 'tokens'])
            ->where('user_id', auth()->id())
            ->findOrFail($groupId);

        DB::transaction(function () use ($group) {
            $group->members()->detach();
            $group->tokens()->delete();
            $group->delete();
        });

        $databaseGroups = GroupDatabase::databaseGroups(auth()->id(), $group->team_id);

        $databaseNotInGroup = UserDatabase::where('user_id', auth()->id())
            ->whereDoesntHave('groups')
            ->get(['id', 'database_name']);

        return redirect()->back()->with([
            'success' => 'Group deleted successfully',
            'databaseGroups' => $databaseGroups,
            'databaseNotInGroup' => $databaseNotInGroup
        ]);
    }

    public function addDatabasesToGroup(GroupDatabase $group, Request $request)
    {
        $validated = $request->validate([
            'databases' => 'required|array|min:1',
            'databases.*' => [
                'required',
                'integer',
                function ($attribute, $value, $fail) use ($group) {
                    $exists = UserDatabase::where('id', $value)
                        ->where('user_id', auth()->id())
                        ->whereDoesntHave('groups', fn($q) => $q->where('group_id', $group->id))
                        ->exists();

                    if (!$exists) {
                        $fail('Invalid database selected.');
                    }
                },
            ],
        ]);

        DB::transaction(function () use ($group, $validated) {
            $group->members()->syncWithoutDetaching($validated['databases']);
        });

        return redirect()->back()->with([
            'success' => 'Databases added successfully'
        ]);
    }

    public function deleteDatabaseFromGroup(GroupDatabase $group, UserDatabase $database)
    {
        $group->members()->detach($database);

        return redirect()->back()->with([
            'success' => 'Database removed successfully'
        ]);
    }
}
