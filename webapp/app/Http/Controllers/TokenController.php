<?php

namespace App\Http\Controllers;

use App\ActivityType;
use App\Models\GroupDatabase;
use App\Models\User;
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
            'database.groups' => fn($q) => $q->whereHas('team', fn($q) => $q->where('id', $teamId))->with('team:id,name'),
            'user:id,name'
        ])
            ->whereHas('database.groups.team', fn($q) => $q->where('id', $teamId))
            ->when(!auth()->user()->hasRole('Super Admin') && !auth()->user()->hasRole('Team Manager'), fn($q) => $q->where('user_id', $userId))
            ->paginate(10);

        // Batched lookup for display names (no per-row queries, no 500 on
        // dangling created_by references).
        $creators = User::whereIn('id', collect($userDatabaseTokens->items())
                ->pluck('created_by')
                ->filter()
                ->unique())
            ->pluck('name', 'id');

        $userDatabaseTokens = $userDatabaseTokens
            ->through(function ($token) use ($creators) {
                $expirationDate = $token->expiration_day !== 0 ? now()->addDays($token->expiration_day) : null;

                // Get team from filtered groups
                $team = $token->database->groups->first()?->team;

                $createdByName = $creators->get($token->created_by) ?? 'Unknown';

                return [
                    ...$token->toArray(),
                    'full_access_token' => '••••••••' . substr((string) $token->full_access_token, -8),
                    'read_only_token' => '••••••••' . substr((string) $token->read_only_token, -8),
                    'created_by' => $createdByName,
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

        $allUsers = User::select('id', 'name')
            ->with('roles')
            ->when(auth()->user()->hasRole('Database Maintainer'), fn($q) => $q->whereHas('roles', fn($q) => $q->whereIn('name', ['Member', 'Database Maintainer'])))
            ->get();

        return Inertia::render('dashboard-token', [
            'allUsers' => $allUsers,
            'mostUsedDatabases' => $databases,
            'isAllTokenized' => $databases->every('is_tokenized'),
            'userDatabaseTokens' => $userDatabaseTokens
        ]);
    }

    public function createToken(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string',
            'databaseId' => 'required|integer|exists:user_databases,id',
            'expiration' => 'required|integer',
            'userId' => 'required|integer|exists:users,id'
        ]);

        $user = User::find($validated['userId']);

        $pair = (new DatabaseTokenGenerator())->generateToken(
            $validated['databaseId'],
            $validated['userId'],
            $validated['expiration']
        );

        if (!$pair) {
            return redirect()->back()
                ->with('error', 'Failed to generate tokens');
        }

        // The database stores only SHA-256 hashes of the bearer tokens.
        $formData = [
            'user_id' => $validated['userId'],
            'database_id' => $validated['databaseId'],
            'name' => "Token for {$user->name}",
            'full_access_token' => hash('sha256', $pair['full_access_token']),
            'read_only_token' => hash('sha256', $pair['read_only_token']),
            'expiration_day' => $validated['expiration'],
            'created_by' => auth()->id()
        ];

        try {
            $tokenModel = UserDatabaseToken::updateOrCreate(
                [
                    'user_id' => $validated['userId'],
                    'database_id' => $validated['databaseId'],
                ],
                $formData
            );

            // Plaintext exists only in this response; the session carries it
            // to the page once — later reads show only the stored hashes.
            $tokenModel->full_access_token = $pair['full_access_token'];
            $tokenModel->read_only_token = $pair['read_only_token'];


            log_user_activity(
                auth()->user(),
                ActivityType::DATABASE_TOKEN_CREATE,
                "Token created from " . $request->ip(),
                activity_request_context()
            );

            return redirect()->back()->with([
                'success' => 'Token created/updated successfully',
                'newToken' => $tokenModel,
                'databaseGroups' => GroupDatabase::databaseGroups(auth()->id(), session('team_databases')['team_id'] ?? null),
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to save token');
        }
    }

    public function deleteToken(int $tokenId)
    {
        $token = UserDatabaseToken::find($tokenId);
        abort_if(!$token, 404, 'Token not found');

        $isOwn = (int) $token->user_id === (int) auth()->id();
        $canManage = auth()->user()->hasPermission('manage-database-tokens')
            || auth()->user()->hasPermission('manage-users');
        abort_unless($isOwn || $canManage, 403, 'Unauthorized');

        $token->delete();


        log_user_activity(
            auth()->user(),
            ActivityType::DATABASE_TOKEN_DELETE,
            "Token deleted from " . request()->ip(),
            activity_request_context()
        );

        return redirect()->back()->with([
            'success' => 'Token deleted successfully',
            'userDatabaseTokens' => UserDatabaseToken::where('user_id', auth()->id())->get()
        ]);
    }
}
