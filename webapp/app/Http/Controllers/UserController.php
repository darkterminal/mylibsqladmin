<?php

namespace App\Http\Controllers;

use App\ActivityType;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\UserActivityLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;

class UserController extends Controller
{
    public function index()
    {
        $users = User::allUsers();
        return Inertia::render('user/dashboard-user', [
            'users' => $users
        ]);
    }

    public function show(User $user)
    {
        $detail = User::detail($user);
        return Inertia::render('user/dashboard-user-detail', [
            'userData' => $detail,
        ]);
    }

    public function create()
    {
        $roles = Role::all();
        $teams = Team::all();
        $permissionLevels = [
            ['id' => 'super-admin', 'name' => 'Super Admin'],
            ['id' => 'team-manager', 'name' => 'Team Manager'],
            ['id' => 'database-maintainer', 'name' => 'Database Maintainer'],
            ['id' => 'member', 'name' => 'Member']
        ];
        return Inertia::render('user/dashboard-user-create', [
            'availableRoles' => $roles,
            'availableTeams' => $teams,
            'permissionLevels' => $permissionLevels
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users',
            'email' => 'required|email|unique:users',
            'password' => 'required|confirmed|min:8',
            'teamSelections' => 'array',
            'teamPermissions' => 'array',
            'roleSelection' => 'required|exists:roles,id'
        ]);

        $user = User::create($request->only('name', 'username', 'email', 'password'));

        if ($request->teamSelections && $request->teamPermissions) {
            $teamsWithPermissions = collect($request->teamSelections)
                ->mapWithKeys(fn($teamId) => [$teamId => ['permission_level' => $request->teamPermissions[$teamId]]]);
            $user->teams()->sync($teamsWithPermissions);
        }

        $user->roles()->attach($request->roleSelection);

        $location = get_ip_location($request->ip());

        log_user_activity(
            auth()->user(),
            ActivityType::USER_CREATE,
            "User {$user->name} created from " . $request->ip(),
            [
                'ip' => $request->ip(),
                'device' => $request->userAgent(),
                'country' => $location['country'],
                'city' => $location['city'],
            ]
        );

        return redirect()->route('dashboard.users');
    }

    public function edit(User $user)
    {
        $detail = User::detail($user);
        $roles = Role::all();
        $teams = Team::all();
        $permissionLevels = [
            ['id' => 'super-admin', 'name' => 'Super Admin'],
            ['id' => 'team-manager', 'name' => 'Team Manager'],
            ['id' => 'database-maintainer', 'name' => 'Database Maintainer'],
            ['id' => 'member', 'name' => 'Member']
        ];

        $location = get_ip_location(request()->ip());

        log_user_activity(
            auth()->user(),
            ActivityType::USER_UPDATE,
            "User {$user->name} updated from " . request()->ip(),
            [
                'ip' => request()->ip(),
                'device' => request()->userAgent(),
                'country' => $location['country'],
                'city' => $location['city'],
            ]
        );

        return Inertia::render('user/dashboard-user-edit', [
            'user' => $detail,
            'availableRoles' => $roles,
            'availableTeams' => $teams,
            'permissionLevels' => $permissionLevels
        ]);
    }

    public function update(Request $request, User $user)
    {
        $request->validate([
            'name' => ['required', 'string', 'max:255'],
            "username" => ["required", "string", "max:255", "unique:users,username,{$user->id}"],
            "email" => ["required", "email", "max:255", "unique:users,email,{$user->id}"],
            'teamSelections' => ['array'],
            'teamPermissions' => ['array'],
            'roleSelections' => ['required', 'integer'],
        ]);

        $user->update($request->only('name', 'username', 'email'));

        $teamsWithPermissions = collect($request->teamSelections)->mapWithKeys(fn($teamId) => [
            $teamId => ['permission_level' => $request->teamPermissions[$teamId]]
        ])->toArray();

        $user->teams()->sync($teamsWithPermissions);

        $user->roles()->sync([$request->roleSelections]);

        $location = get_ip_location(request()->ip());

        log_user_activity(
            auth()->user(),
            ActivityType::USER_UPDATE,
            "User {$user->name} updated from " . request()->ip(),
            [
                'ip' => request()->ip(),
                'device' => request()->userAgent(),
                'country' => $location['country'],
                'city' => $location['city'],
            ]
        );

        return redirect()->route('user.show', $user->id);
    }

    public function destroy(User $user)
    {
        $name = $user->name;
        $user->delete();
        $location = get_ip_location(request()->ip());

        log_user_activity(
            auth()->user(),
            ActivityType::USER_DELETE,
            "User {$name} deleted from " . request()->ip(),
            [
                'ip' => request()->ip(),
                'device' => request()->userAgent(),
                'country' => $location['country'],
                'city' => $location['city'],
            ]
        );

        return redirect()->route('dashboard.users');
    }

    public function archive()
    {
        $users = User::allUserArchives();
        return Inertia::render('user/dashboard-user-archived', [
            'users' => $users
        ]);
    }

    public function restoreUser(User $user)
    {
        $name = $user->name;
        $user->restore();

        $location = get_ip_location(request()->ip());

        log_user_activity(
            auth()->user(),
            ActivityType::USER_RESTORE,
            "User {$name} restored from " . request()->ip(),
            [
                'ip' => request()->ip(),
                'device' => request()->userAgent(),
                'country' => $location['country'],
                'city' => $location['city'],
            ]
        );

        return redirect()->route('dashboard.users');
    }

    public function forceDelete(User $user)
    {
        $name = $user->name;
        $user->forceDelete();

        $location = get_ip_location(request()->ip());

        log_user_activity(
            auth()->user(),
            ActivityType::USER_FORCE_DELETE,
            "User {$name} permanently deleted from " . request()->ip(),
            [
                'ip' => request()->ip(),
                'device' => request()->userAgent(),
                'country' => $location['country'],
                'city' => $location['city'],
            ]
        );

        return redirect()->route('dashboard.users');
    }

    public function deactivate(User $user)
    {
        $name = $user->name;
        $user->deactivate();

        $location = get_ip_location(request()->ip());

        log_user_activity(
            auth()->user(),
            ActivityType::USER_DEACTIVATE,
            "User {$name} deactivated from " . request()->ip(),
            [
                'ip' => request()->ip(),
                'device' => request()->userAgent(),
                'country' => $location['country'],
                'city' => $location['city'],
            ]
        );

        return redirect()->back();
    }

    public function activate(User $user)
    {
        $name = $user->name;
        $user->activate();

        $location = get_ip_location(request()->ip());

        log_user_activity(
            auth()->user(),
            ActivityType::USER_REACTIVATE,
            "User {$name} activated from " . request()->ip(),
            [
                'ip' => request()->ip(),
                'device' => request()->userAgent(),
                'country' => $location['country'],
                'city' => $location['city'],
            ]
        );

        return redirect()->back();
    }

    public function activities(User $user)
    {
        $activities = UserActivityLogger::getActivitiesForUser($user, 10);
        return Inertia::render('user/dashboard-user-activities', [
            'activities' => $activities
        ]);
    }
}
