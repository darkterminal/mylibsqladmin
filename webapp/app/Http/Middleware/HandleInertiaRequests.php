<?php

namespace App\Http\Middleware;

use App\Models\GroupDatabase;
use App\Models\Team;
use App\Models\User;
use App\Models\UserDatabase;
use App\Models\UserDatabaseToken;
use App\Services\SqldService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Inertia\Middleware;
use PHPUnit\Framework\Attributes\Group;
use Tighten\Ziggy\Ziggy;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $currentTeamDatabases = session('team_databases') ?? [];

        if (auth()->check() && env('LIBSQL_LOCAL_INSTANCE') === false) {
            $teamId = (int) $currentTeamDatabases['team_id'];
            $userId = $request->user()->id;
            $databases = SqldService::getDatabases(false, $userId);

            if (
                !GroupDatabase::where('user_id', $userId)->whereHas('members', function ($query) use ($databases) {
                    $query->whereIn('database_id', Arr::pluck($databases, 'id'));
                })->exists()
            ) {
                $remoteDatabase = Arr::pluck($databases, 'id');

                $group = GroupDatabase::firstOrCreate([
                    'user_id' => $userId,
                    'team_id' => $teamId,
                    'name' => 'default',
                    'created_by' => $userId
                ]);

                $group->members()->sync($remoteDatabase);

                Team::setTeamDatabases($userId, $teamId);
                $currentTeamDatabases = session('team_databases');
            }
            logger()->debug("Remote database already synced for user $userId");
        }

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'csrfToken' => csrf_token(),
            'auth' => [
                'user' => function () use ($request) {
                    if (!$user = $request->user()) {
                        return null;
                    }

                    if ($user->teams()->doesntExist()) {
                        $team = Team::firstOrCreate([
                            "name" => "{$user->username} space",
                            'description' => 'Personal workspace'
                        ]);

                        $team->members()->attach($user->id, ['permission_level' => 'super-admin']);
                    }

                    return $user->append('permission_names')
                        ->load('teams:id,name,description')
                        ->only('id', 'username', 'name', 'email', 'role', 'permission_names', 'teams');
                },
                'permissions' => fn() => $request->user() ? [
                    'abilities' => $request->user()->getAllPermissions(),
                    'role' => $request->user()->roles()->get()->pluck('name')
                ] : null,
            ],
            'flash' => [
                'success' => fn() => $request->session()->get('success'),
                'error' => fn() => $request->session()->get('error'),
                'newToken' => fn() => $request->session()->get('newToken'),
                'newTeam' => fn() => $request->session()->get('newTeam'),
                'updatedGroup' => fn() => $request->session()->get('updatedGroup'),
                'databaseGroups' => fn() => $request->session()->get('databaseGroups'),
                'databaseNotInGroup' => fn() => $request->Session()->get('databaseNotInGroup'),
            ],
            'ziggy' => fn() => [
                ...(new Ziggy())->toArray(),
                'location' => $request->url(),
                'query' => $request->query(),
            ],
            'databases' => fn() => $currentTeamDatabases['databases'] ?? [],
            'groups' => fn() => $currentTeamDatabases['groups'] ?? [],
            'invitation' => fn() => session('valid_invitation') ?? null,
            'configs' => [
                'sqldHost' => config('mylibsqladmin.libsql.connection.host'),
                'sqldPort' => config('mylibsqladmin.libsql.connection.port'),
            ]
        ];
    }
}
