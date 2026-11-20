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
    public function index(Request $request)
    {
        $users = User::allUsers($request->query('search'));
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

        $user = DB::transaction(function () use ($request) {
            $user = User::create($request->only('name', 'username', 'email', 'password'));

            if ($request->teamSelections && $request->teamPermissions) {
                $teamsWithPermissions = collect($request->teamSelections)
                    ->mapWithKeys(fn($teamId) => [$teamId => ['permission_level' => $request->teamPermissions[$teamId]]]);
                $user->teams()->sync($teamsWithPermissions);
            }

            $user->roles()->attach($request->roleSelection);

            return $user;
        });


        log_user_activity(
            auth()->user(),
            ActivityType::USER_CREATE,
            "User {$user->name} created from " . $request->ip(),
            activity_request_context()
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
            'teamPermissions.*' => ['required', 'string', Rule::in(Role::KEYS)],
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
            log_user_activity(
                auth()->user(),
                ActivityType::USER_UPDATE,
                "User {$user->name} updated from " . $request->ip(),
                activity_request_context($request)
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
        abort_if($user->id === auth()->user()->id, 403, 'You cannot delete your own account.');

        $name = $user->name;
        $user->delete();

        log_user_activity(
            auth()->user(),
            ActivityType::USER_DELETE,
            "User {$name} deleted from " . request()->ip(),
            activity_request_context()
        );

        return redirect()->route('dashboard.users');
    }

    public function archive(Request $request)
    {
        $users = User::allUserArchives($request->query('search'));
        return Inertia::render('user/dashboard-user-archived', [
            'users' => $users
        ]);
    }

    public function restoreUser(User $user)
    {
        $name = $user->name;
        $user->restore();


        log_user_activity(
            auth()->user(),
            ActivityType::USER_RESTORE,
            "User {$name} restored from " . request()->ip(),
            activity_request_context()
        );

        return redirect()->route('dashboard.users');
    }

    public function forceDelete(Request $request, User $user)
    {
        abort_if($user->id === auth()->user()->id, 403, 'You cannot permanently delete your own account.');

        // Permanent deletion is irreversible — require the acting admin's own
        // password before anything happens. current_password validates
        // against the authenticated user's stored hash.
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $name = $user->name;
        $user->forceDelete();


        log_user_activity(
            auth()->user(),
            ActivityType::USER_FORCE_DELETE,
            "User {$name} permanently deleted from " . request()->ip(),
            activity_request_context()
        );

        return redirect()->route('dashboard.users');
    }

    public function deactivate(User $user)
    {
        abort_if($user->id === auth()->user()->id, 403, 'You cannot deactivate your own account.');

        $name = $user->name;
        $user->deactivate();
        // Revoke outstanding Sanctum/API tokens so deactivated users cannot
        // continue using CLI or token-based access. Web sessions persist until
        // next sign-in; the auth middleware below blocks new sign-ins for
        // inactive users.
        $user->tokens()->delete();


        log_user_activity(
            auth()->user(),
            ActivityType::USER_DEACTIVATE,
            "User {$name} deactivated from " . request()->ip(),
            activity_request_context()
        );

        return redirect()->back();
    }

    public function activate(User $user)
    {
        $name = $user->name;
        $user->activate();


        log_user_activity(
            auth()->user(),
            ActivityType::USER_REACTIVATE,
            "User {$name} activated from " . request()->ip(),
            activity_request_context()
        );

        return redirect()->back();
    }

    public function activities(User $user)
    {
        $activities = UserActivityLogger::getActivitiesForUser($user, 50);
        return Inertia::render('user/dashboard-user-activities', [
            'activities' => $activities
        ]);
    }
}
