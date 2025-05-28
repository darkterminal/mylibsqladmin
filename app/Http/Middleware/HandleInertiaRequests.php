<?php

namespace App\Http\Middleware;

use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Middleware;
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

        return [
            ...parent::share($request),
            'name' => config('app.name'),
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
