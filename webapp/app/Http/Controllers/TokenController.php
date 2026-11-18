<?php

namespace App\Http\Controllers;

use App\ActivityType;
use App\Models\GroupDatabase;
use App\Models\User;
use App\Models\UserDatabase;
use App\Models\Team;
use App\Models\UserDatabaseToken;
use App\Services\DatabaseTokenGenerator;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TokenController extends Controller
{
    public function index(Request $request)
    {
        $userId = auth()->id();
        $teamId = session('team_databases.team_id');
        $search = trim((string) $request->query('search', ''));

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

        $like = $search !== '' ? '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search) . '%' : null;

        // Main tokens query with team filtering
        $userDatabaseTokens = UserDatabaseToken::with([
            'database.groups' => fn($q) => $q->whereHas('team', fn($q) => $q->where('id', $teamId))->with('team:id,name'),
            'user:id,name'
        ])
            ->whereHas('database.groups.team', fn($q) => $q->where('id', $teamId))
            ->when(!auth()->user()->hasRole('Super Admin') && !auth()->user()->hasRole('Team Manager'), fn($q) => $q->where('user_id', $userId))
            ->when($like, fn($q) => $q->where(function ($q) use ($like) {
                $q->where('name', 'like', $like)
                    ->orWhereHas('database', fn($d) => $d->where('database_name', 'like', $like))
                    ->orWhereHas('user', fn($u) => $u->where('name', 'like', $like));
            }))
            ->orderByDesc('id')
            ->paginate(10)
            ->withQueryString();

        // Batched lookup for display names (no per-row queries, no 500 on
        // dangling created_by references).
        $creators = User::whereIn('id', collect($userDatabaseTokens->items())
                ->pluck('created_by')
                ->filter()
                ->unique())
            ->pluck('name', 'id');

        $userDatabaseTokens = $userDatabaseTokens
            ->through(function ($token) use ($creators) {
                $createdAt = $token->created_at;
                // Expiry is anchored at creation time, not at render time —
                // the old now()->addDays() drift made every token look fresh daily.
                $expiresAt = $token->expiration_day && $createdAt
                    ? $createdAt->copy()->addDays((int) $token->expiration_day)
                    : null;
                $state = 'never';
                $daysRemaining = null;
                if ($expiresAt) {
                    $state = now()->isAfter($expiresAt) ? 'expired' : 'active';
                    $daysRemaining = (int) now()->diffInDays($expiresAt, false);
                }

                // Get team from filtered groups
                $team = $token->database->groups->first()?->team;

                $createdByName = $creators->get($token->created_by) ?? 'Unknown';

                // The stored hash's tail doubles as the fingerprint — the only
                // stable identifier a hashed token has.
                $fingerprint = substr((string) $token->getRawOriginal('full_access_token'), -8);

                return [
                    ...$token->toArray(),
                    'full_access_token' => '••••••••' . $fingerprint,
                    'read_only_token' => '••••••••' . substr((string) $token->getRawOriginal('read_only_token'), -8),
                    'fingerprint' => $fingerprint,
                    'created_by' => $createdByName,
                    'expiration_day' => (int) $token->expiration_day,
                    'expires_at' => $expiresAt?->format('Y-m-d'),
                    'expiration_state' => $state,
                    'days_remaining' => $daysRemaining,
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
            'userDatabaseTokens' => $userDatabaseTokens
        ]);
    }

    public function createToken(Request $request)
    {
        $validated = $request->validate([
            'name' => 'nullable|string|max:120',
            'databaseId' => 'required|integer|exists:user_databases,id',
            'expiration' => 'required|integer|min:0',
            'userId' => 'required|integer|exists:users,id'
        ]);

        $user = User::find($validated['userId']);

        // The submitted name is honoured when it is a real string; otherwise
        // fall back to the per-user default so the list never shows blanks.
        $requestedName = trim((string) ($validated['name'] ?? ''));
        $tokenName = $requestedName !== '' ? $requestedName : "Token for {$user->name}";

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
            'name' => $tokenName,
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
                'databaseGroups' => GroupDatabase::databaseGroups(auth()->id(), Team::currentTeamDatabases()['team_id'] ?? null),
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', 'Failed to save token');
        }
    }

    public function deleteToken(int $tokenId)
    {
        $token = UserDatabaseToken::find($tokenId);
        abort_if(!$token, 404, 'Token not found');

        $db = $token->database;

        $isOwn = (int) $token->user_id === (int) auth()->id();
        $isSuperAdmin = auth()->user()->hasRole('Super Admin');
        $isDatabaseOwner = $db && (int) $db->user_id === (int) auth()->id();
        $isOwningTeamMember = $db && $db->team_id && \DB::table('team_user')
            ->where('team_id', $db->team_id)
            ->where('user_id', auth()->id())
            ->exists();
        $canManageDatabase = $isDatabaseOwner || $isOwningTeamMember;
        $canManage = $canManageDatabase && (
            auth()->user()->hasPermission('manage-database-tokens')
            || auth()->user()->hasPermission('manage-users')
        );
        abort_unless($isOwn || $isSuperAdmin || $canManage, 403, 'Unauthorized');

        // Permanent removal — a leaked/rotated credential must not linger.
        $token->forceDelete();


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
