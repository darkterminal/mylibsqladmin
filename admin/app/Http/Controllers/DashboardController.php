<?php

namespace App\Http\Controllers;

use App\Models\GroupDatabase;
use App\Models\QueryMetric;
use App\Models\TopQuery;
use App\Models\UserDatabase;
use App\Models\UserDatabaseToken;
use App\Services\DatabaseTokenGenerator;
use App\Services\SqldService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class DashboardController extends Controller
{
    public function index()
    {
        $databases = SqldService::getDatabases();
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
        SqldService::createDatabase($request->database, $request->isSchema);
        $databases = SqldService::getDatabases();
        return redirect()->back()->with('databases', $databases);
    }

    public function deleteDatabase(string $database)
    {
        SqldService::deleteDatabase($database);
        $databases = SqldService::getDatabases();
        return redirect()->back()->with('databases', $databases);
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
        $allTokenized = collect($databases)->every(fn($database) => $database['is_tokenized']);
        $userDatabaseTokens = UserDatabaseToken::with(['database'])
            ->where('user_id', auth()->user()->id)
            ->get()
            ->map(function ($token) {
                $expirationDate = Carbon::now()->addDays($token->expiration_day)->format('Y-m-d');

                return [
                    ...$token->toArray(),
                    'expiration_day' => Carbon::now()->isAfter(Carbon::parse($expirationDate)) ? "Expired" : $expirationDate
                ];
            });

        return Inertia::render('dashboard-token', [
            'mostUsedDatabases' => $databases,
            'isAllTokenized' => $allTokenized,
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
            $save = UserDatabaseToken::updateOrCreate(
                [
                    'database_id' => $validated['databaseId'],
                    'user_id' => auth()->id(),
                ],
                $formData
            );

            return redirect()->back()
                ->with('success', 'Token created/updated successfully');
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', 'Failed to save token: ' . $e->getMessage());
        }
    }

    public function deleteToken(int $tokenId)
    {
        UserDatabaseToken::where('id', $tokenId)->delete();
        return redirect()->back()
            ->with('success', 'Token deleted successfully');
    }

    public function indexGroup()
    {
        $databaseGroups = GroupDatabase::withCount('members')
            ->with([
                'user:id,name',
                'members' => function ($query) {
                    $query->with('tokens');
                }
            ])
            ->where('user_id', auth()->id())
            ->latest()
            ->get()
            ->map(function ($group) {
                return [
                    'id' => $group->id,
                    'name' => $group->name,
                    'members_count' => $group->members_count,
                    'user' => $group->user,
                    'members' => $group->members->map(fn($m) => [
                        'id' => $m->id,
                        'database_name' => $m->database_name,
                        'is_schema' => $m->is_schema
                    ]),
                    'database_tokens' => $group->members->flatMap(
                        fn($member) =>
                        $member->tokens->map(fn($token) => [
                            'id' => $token->id,
                            'name' => $token->name,
                            'full_access_token' => $token->full_access_token,
                            'read_only_token' => $token->read_only_token,
                            'expiration_day' => $token->expiration_day,
                            'database_id' => $token->database_id,
                            'user_id' => $token->user_id,
                        ])
                    )
                ];
            });

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
            'name' => 'required|string|max:255',
            'expiration' => 'required|integer|min:1|max:365'
        ]);

        $tokenGenerator = (new DatabaseTokenGenerator())->generateToken(
            $validated['name'],
            auth()->id(),
            $validated['expiration']
        );

        $token = DB::transaction(function () use ($group, $validated, $tokenGenerator) {
            return $group->tokens()->create([
                'name' => $validated['name'],
                'full_access_token' => $tokenGenerator['full_access_token'],
                'read_only_token' => $tokenGenerator['read_only_token'],
                'expiration_day' => $validated['expiration']
            ]);
        });

        return redirect()->back()->with([
            'success' => 'Group token created successfully',
            'newToken' => $token
        ]);
    }

    public function createGroup(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
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

        $group = DB::transaction(function () use ($validated) {
            $group = GroupDatabase::create([
                'name' => $validated['name'],
                'user_id' => auth()->id(),
            ]);

            $group->members()->sync($validated['databases']);

            return $group->load(['members', 'tokens', 'user'])
                ->loadCount('members');
        });

        return redirect()->back()->with([
            'success' => 'Group created successfully',
            'newGroup' => [
                'id' => $group->id,
                'name' => $group->name,
                'members_count' => $group->members_count,
                'created_at' => $group->created_at,
                'user' => $group->user,
                'members' => $group->members->map(fn($m) => [
                    'id' => $m->id,
                    'database_name' => $m->database_name
                ]),
                'database_tokens' => []
            ]
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

        return redirect()->back()
            ->with('success', 'Group deleted successfully');
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

        return redirect()->back()->with('success', 'Databases added successfully');
    }
}
