<?php

namespace App\Http\Middleware;

use App\Models\GroupDatabase;
use App\Models\QueryMetric;
use App\Models\Team;
use App\Models\UserDatabase;
use App\Services\SqldService;
use Illuminate\Foundation\Inspiring;
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
        [$message, $author] = str(Inspiring::quotes()->random())->explode('-');

        $currentTeamDatabases = session('team_databases') ?? [];

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'quote' => ['message' => trim($message), 'author' => trim($author)],
            'auth' => [
                'user' => function () use ($request) {
                    if (!$user = $request->user()) {
                        return null;
                    }

                    if ($user->teams()->doesntExist()) {
                        $team = Team::firstOrCreate(
                            ["name" => "{$user->username} space"],
                            ['description' => 'Personal workspace']
                        );

                        $user->teams()->syncWithoutDetaching([
                            $team->id => ['permission_level' => 'admin']
                        ]);
                    }

                    return $user->append('permission_names')
                        ->load('teams:id,name,description')
                        ->only('id', 'username', 'name', 'email', 'role', 'permission_names', 'teams');
                },
                'permissions' => fn() => $request->user() ? [
                    'abilities' => $request->user()->getAllPermissions(),
                    'can' => [
                        'manageTeams' => $request->user()->can('manage-teams'),
                        'createTeam' => $request->user()->can('create-teams'),
                        'manageGroupDatabases' => $request->user()->can('manage-group-databases'),
                        'manageGroupDatabaseTokens' => $request->user()->can('manage-group-database-tokens'),
                        'manageDatabaseTokens' => $request->user()->can('manage-database-tokens'),
                        'manageTeamGroups' => $request->user()->can('manage-team-groups'),
                        'accessTeamDatabases' => $request->user()->can('access-team-databases'),
                    ]
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
        ];
    }
}
