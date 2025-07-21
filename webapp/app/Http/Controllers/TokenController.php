<?php

namespace App\Http\Controllers;

use App\ActivityType;
use App\Models\GroupDatabase;
use App\Models\UserDatabase;
use App\Models\UserDatabaseToken;
use App\Services\DatabaseTokenGenerator;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TokenController extends Controller
{
    public function index()
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
                $expirationDate = $token->expiration_day !== 0 ? now()->addDays($token->expiration_day) : null;

                // Get team from filtered groups
                $team = $token->database->groups->first()?->team;

                return [
                    ...$token->toArray(),
                    'expiration_day' => $expirationDate
                        ? now()->isAfter($expirationDate) ? "Expired" : $expirationDate->format('Y-m-d')
                        : "Never",
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
                ],
                $formData
            );

            $location = get_ip_location($request->ip());

            log_user_activity(
                auth()->user(),
                ActivityType::DATABASE_TOKEN_CREATE,
                "Token created from " . $request->ip(),
                [
                    'ip' => $request->ip(),
                    'device' => $request->userAgent(),
                    'country' => $location['country'],
                    'city' => $location['city'],
                ]
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

        $location = get_ip_location(request()->ip());

        log_user_activity(
            auth()->user(),
            ActivityType::DATABASE_TOKEN_DELETE,
            "Token deleted from " . request()->ip(),
            [
                'ip' => request()->ip(),
                'device' => request()->userAgent(),
                'country' => $location['country'],
                'city' => $location['city'],
            ]
        );

        return redirect()->back()->with([
            'success' => 'Token deleted successfully',
            'userDatabaseTokens' => UserDatabaseToken::where('user_id', auth()->id())->get()
        ]);
    }
}
