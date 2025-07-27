<?php

namespace App\Http\Controllers;

use App\ActivityType;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\UserActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
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
        // 1. More specific and robust validation
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users')->ignore($user->id),
            ],
            // Validate that the role ID actually exists in the database
            'roleSelections' => ['required', 'integer', 'exists:roles,id'],
            // Validate team selections
            'teamSelections' => ['present', 'array'],
            'teamSelections.*' => ['integer', 'exists:teams,id'], // Each team must exist
            // Validate team permissions
            'teamPermissions' => ['present', 'array'],
            'teamPermissions.*' => ['required', 'string', Rule::in(['super-admin', 'team-manager', 'database-maintainer', 'member'])],
        ]);

        try {
            // 2. Use a database transaction for data integrity
            DB::transaction(function () use ($user, $validated) {
                // Update user details from validated data
                $user->update([
                    'name' => $validated['name'],
                    'username' => $validated['username'],
                    'email' => $validated['email'],
                ]);

                // 3. Safer and cleaner way to build the sync data for teams
                $teamsWithPermissions = [];
                foreach ($validated['teamSelections'] as $teamId) {
                    // Ensure the permission for this team was actually sent
                    if (isset($validated['teamPermissions'][$teamId])) {
                        $teamsWithPermissions[$teamId] = [
                            'permission_level' => $validated['teamPermissions'][$teamId]
                        ];
                    }
                }

                // Sync teams and roles
                $user->teams()->sync($teamsWithPermissions);
                $user->roles()->sync([$validated['roleSelections']]);
            });

            // 4. Move success-dependent logic outside the transaction
            $location = get_ip_location($request->ip());
            log_user_activity(
                auth()->user(),
                ActivityType::USER_UPDATE,
                "User {$user->name} updated from " . $request->ip(),
                [
                    'ip' => $request->ip(),
                    'device' => $request->userAgent(),
                    'country' => $location['country'] ?? 'Unknown',
                    'city' => $location['city'] ?? 'Unknown',
                ]
            );

            // 5. Add a success message for better user feedback
            return redirect()->route('user.show', $user->id)->with('success', 'User updated successfully.');

        } catch (\Throwable $th) {
            // Log the actual error for debugging purposes
            \Log::error('User update failed: ' . $th->getMessage());
            return back()->with('error', 'An unexpected error occurred. Please try again.');
        }
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
