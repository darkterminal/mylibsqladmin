<?php

namespace App\Http\Controllers;

use App\ActivityType;
use App\Events\TeamDatabasesRequested;
use App\Jobs\SendTeamInvitation;
use App\Models\ActivityLog;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        $user = auth()->user();

        $teamsQuery = Team::with([
            'members',
            'invitations.inviter',
            'groups.members' => function ($query) {
                $query->with(['latestActivity', 'user'])
                    ->select('id', 'database_name', 'is_schema', 'user_id', 'created_at');
            },
            'recentActivities.user'
        ]);

        if ($user->hasRole('Super Admin')) {
            $teams = $teamsQuery->get();
        } elseif ($user->hasPermission('manage-teams')) {
            $teams = $teamsQuery->whereHas('members', fn($q) => $q->where('user_id', $user->id))->get();
        } else {
            $teams = $teamsQuery->whereHas('members', fn($q) => $q->where('user_id', $user->id))->get();
        }

        $teamData = $teams->map(function ($team) use ($user) {
            $isTeamMember = $team->members->contains('id', $user->id);
            $canAccessDatabases = $user->hasRole('Super Admin') ||
                ($user->hasPermission('manage-teams') || $isTeamMember);

            $pendingInvitations = $team->invitations
                ->where('expires_at', '>', now())
                ->map(fn($invite) => [
                    'id' => $invite->id,
                    'name' => $invite->name,
                    'email' => $invite->email,
                    'inviter' => $invite->inviter->name,
                    'expires_at' => $invite->expires_at->diffForHumans(),
                    'permission_level' => $invite->permission_level,
                    'sent_at' => $invite->created_at->format('M d, Y H:i')
                ]);

            return [
                'id' => $team->id,
                'name' => $team->name,
                'description' => $team->description,
                'members' => $team->members->count(),
                'team_members' => $team->members->map(fn($member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $member->pivot->permission_level,
                ]),
                'pending_invitations' => $pendingInvitations,
                'groups' => $canAccessDatabases ? $team->groups->map(fn($group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'databases' => $group->members->map(fn($database) => [
                        'id' => $database->id,
                        'name' => $database->database_name,
                        'type' => $this->determineDatabaseType($database->is_schema),
                        'lastActivity' => $database->latestActivity?->created_at->diffForHumans() ?? 'No activity'
                    ])
                ]) : [],
                'recentActivity' => $canAccessDatabases ? $team->recentActivities->map(fn($activity) => [
                    'id' => $activity->id,
                    'user' => $activity->user->name,
                    'action' => $activity->action,
                    'database' => $activity->database?->database_name,
                    'time' => $activity->created_at->diffForHumans()
                ]) : []
            ];
        });

        return Inertia::render('dashboard-team', [
            'teams' => $teamData
        ]);
    }

    private function determineDatabaseType($isSchema)
    {
        if (is_numeric($isSchema) && (int) $isSchema === 1) {
            return 'schema';
        }

        if (is_numeric($isSchema) && (int) $isSchema === 0) {
            return 'standalone';
        }

        return 'child';
    }

    public function createTeam(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);

        try {
            $team = Team::create([
                'name' => $validated['name'],
                'description' => $validated['description'],
            ]);

            $team->members()->attach(auth()->id(), [
                'permission_level' => Role::getRoleKey(auth()->user()->getRoleAttribute())
            ]);

            TeamDatabasesRequested::dispatch(auth()->id(), $team->id);

            $location = get_ip_location($request->ip());

            log_user_activity(
                auth()->user(),
                ActivityType::TEAM_CREATE,
                "Team {$team->name} created from {$request->ip()}",
                [
                    'ip' => $request->ip(),
                    'device' => $request->userAgent(),
                    'country' => $location['country'],
                    'city' => $location['city'],
                ]
            );

            return redirect()->back()->with([
                'success' => 'Team created successfully',
                'newTeam' => $team->id
            ]);
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function updateTeam(Request $request, $teamId)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);

        try {
            $team = Team::findOrFail($teamId);
            $team->name = $validated['name'];
            $team->description = $validated['description'];
            $team->save();

            Team::setTeamDatabases(auth()->id(), 'null');

            $location = get_ip_location($request->ip());

            log_user_activity(
                auth()->user(),
                ActivityType::TEAM_UPDATE,
                "Team {$team->name} updated from {$request->ip()}",
                [
                    'ip' => $request->ip(),
                    'device' => $request->userAgent(),
                    'country' => $location['country'],
                    'city' => $location['city'],
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Team updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
        }
    }

    public function deleteTeam(Request $request, $teamId)
    {
        try {
            $team = Team::findOrFail($teamId);
            $teamName = $team->name;
            $team->delete();

            Team::setTeamDatabases(auth()->id(), 'null');

            $location = get_ip_location($request->ip());

            log_user_activity(
                auth()->user(),
                ActivityType::TEAM_DELETE,
                "Team {$teamName} deleted from {$request->ip()}",
                [
                    'ip' => $request->ip(),
                    'device' => $request->userAgent(),
                    'country' => $location['country'],
                    'city' => $location['city'],
                ]
            );

            return response()->json([
                'success' => true,
                'message' => "Team {$teamName} deleted successfully"
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
        }
    }

    public function archiveTeam(Request $request)
    {
        $user = auth()->user();

        $teamsQuery = Team::onlyTrashed()->with([
            'members',
            'groups' => function ($query) {
                $query->onlyTrashed();
            },
            'invitations.inviter',
            'groups.members' => function ($query) {
                $query->onlyTrashed()->with(['latestActivity', 'user'])
                    ->select('id', 'database_name', 'is_schema', 'user_id', 'created_at');
            },
            'recentActivities.user'
        ]);

        if ($user->hasRole('Super Admin')) {
            $teams = $teamsQuery->get();
        } elseif ($user->hasPermission('manage-teams')) {
            $teams = $teamsQuery->whereHas('members', fn($q) => $q->where('user_id', $user->id))->get();
        } else {
            $teams = $teamsQuery->whereHas('members', fn($q) => $q->where('user_id', $user->id))->get();
        }

        $teamData = $teams->map(function ($team) use ($user) {
            $isTeamMember = $team->members->contains('id', $user->id);
            $canAccessDatabases = $user->hasRole('Super Admin') ||
                ($user->hasPermission('manage-teams') || $isTeamMember);

            $pendingInvitations = $team->invitations
                ->where('expires_at', '>', now())
                ->map(fn($invite) => [
                    'id' => $invite->id,
                    'name' => $invite->name,
                    'email' => $invite->email,
                    'inviter' => $invite->inviter->name,
                    'expires_at' => $invite->expires_at->diffForHumans(),
                    'permission_level' => $invite->permission_level,
                    'sent_at' => $invite->created_at->format('M d, Y H:i')
                ]);

            return [
                'id' => $team->id,
                'name' => $team->name,
                'description' => $team->description,
                'members' => $team->members->count(),
                'team_members' => $team->members->map(fn($member) => [
                    'id' => $member->id,
                    'name' => $member->name,
                    'email' => $member->email,
                    'role' => $member->pivot->permission_level,
                ]),
                'pending_invitations' => $pendingInvitations,
                'groups' => $canAccessDatabases ? $team->groups->map(fn($group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'databases' => $group->members->map(fn($database) => [
                        'id' => $database->id,
                        'name' => $database->database_name,
                        'type' => $this->determineDatabaseType($database->is_schema),
                        'lastActivity' => $database->latestActivity?->created_at->diffForHumans() ?? 'No activity'
                    ])
                ]) : [],
                'recentActivity' => $canAccessDatabases ? $team->recentActivities->map(fn($activity) => [
                    'id' => $activity->id,
                    'user' => $activity->user->name,
                    'action' => $activity->action,
                    'database' => $activity->database?->database_name,
                    'time' => $activity->created_at->diffForHumans()
                ]) : []
            ];
        });

        return Inertia::render('dashboard-team-archives', [
            'teams' => $teamData
        ]);
    }

    public function restoreTeam(Request $request, Team $team)
    {
        try {
            $team->restore();

            Team::setTeamDatabases(auth()->id(), $team->id);

            $location = get_ip_location($request->ip());
            $user = auth()->user();

            log_user_activity(
                $user,
                ActivityType::TEAM_MEMBER_DELETE,
                "Team member {$user->name} deleted from {$request->ip()}",
                [
                    'ip' => $request->ip(),
                    'device' => $request->userAgent(),
                    'country' => $location['country'],
                    'city' => $location['city'],
                ]
            );

            return redirect()->back()->with('success', "Team {$team->name} restored successfully");
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }

    public function deleteTeamMember(Request $request, Team $team, User $user)
    {
        try {
            $team->members()->detach($user->id);

            Team::setTeamDatabases(auth()->id(), 'null');

            $location = get_ip_location($request->ip());

            log_user_activity(
                auth()->user(),
                ActivityType::TEAM_MEMBER_DELETE,
                "Team member {$user->name} deleted from {$request->ip()}",
                [
                    'ip' => $request->ip(),
                    'device' => $request->userAgent(),
                    'country' => $location['country'],
                    'city' => $location['city'],
                ]
            );

            return response()->json([
                'success' => true,
                'message' => 'Team member deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
        }
    }

    public function getDatabases(Request $request, $teamId)
    {
        try {

            Team::setTeamDatabases(auth()->id(), $teamId);

            TeamDatabasesRequested::dispatch(auth()->id(), $teamId);

            return response()->json([
                'success' => true,
                'message' => 'Databases stored in session'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => $e->getMessage()
            ], $e instanceof \Illuminate\Database\Eloquent\ModelNotFoundException ? 404 : 500);
        }
    }

    public function storeMember(Request $request, Team $team)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'permission_level' => 'required|in:super-admin,team-manager,database-maintener,member'
        ]);

        $team->members()->syncWithoutDetaching([
            $request->user_id => ['permission_level' => $request->permission_level]
        ]);

        Team::setTeamDatabases(auth()->id(), 'null');

        $location = get_ip_location($request->ip());

        log_user_activity(
            auth()->user(),
            ActivityType::TEAM_MEMBER_CREATE,
            "Team member added to {$team->name} from " . $request->ip(),
            [
                'ip' => $request->ip(),
                'device' => $request->userAgent(),
                'country' => $location['country'],
                'city' => $location['city'],
            ]
        );

        return redirect()->back()->with('success', 'Member added successfully');
    }

    public function invite(Request $request, Team $team)
    {
        $request->validate([
            'email' => 'required|email',
            'role' => 'required|in:super-admin,team-manager,database-maintener,member'
        ]);

        $invitation = $team->invitations()->create([
            'name' => $request->name,
            'email' => $request->email,
            'token' => Str::random(64),
            'inviter_id' => auth()->id(),
            'permission_level' => $request->role,
            'expires_at' => now()->addDays(7),
            'created_by' => auth()->id()
        ]);

        // Send notification
        SendTeamInvitation::dispatch($invitation);

        $location = get_ip_location($request->ip());

        log_user_activity(
            auth()->user(),
            ActivityType::TEAM_MEMBER_CREATE,
            "Team member {$request->name} invited to {$team->name} from " . $request->ip(),
            [
                'ip' => $request->ip(),
                'device' => $request->userAgent(),
                'country' => $location['country'],
                'city' => $location['city'],
            ]
        );

        return response()->json([
            'success' => true,
            'message' => 'Invitation sent successfully'
        ]);
    }

    public function acceptInvite($token)
    {
        $invitation = Invitation::where('token', $token)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        // Handle authenticated users
        if (auth()->check()) {
            $this->attachToTeam(auth()->user(), $invitation);
            $invitation->delete();
            return redirect()->route('dashboard.teams')
                ->with('success', 'Successfully joined the team');
        }

        // Handle existing users not logged in
        if ($user = User::where('email', $invitation->email)->first()) {
            $this->attachToTeam($user, $invitation);
            $invitation->delete();
            return redirect()->route('login')
                ->with('status', 'Please login to access the team');
        }

        // Store invitation for registration flow
        session()->put('valid_invitation', $invitation);
        return redirect()->route('register')
            ->with('info', 'Finish registration to join the team');
    }


    protected function attachToTeam(Authenticatable|User $user, Invitation $invitation): void
    {
        $invitation->team->members()->syncWithoutDetaching([
            $user->id => [
                'permission_level' => $invitation->permission_level,
                'created_at' => now(),
                'updated_at' => now()
            ]
        ]);

        Team::setTeamDatabases($user->id, $invitation->team_id);

        $location = get_ip_location(request()->ip());

        log_user_activity(
            $user,
            ActivityType::TEAM_MEMBER_CREATE,
            "Joined team {$invitation->team->name} from " . request()->ip(),
            [
                'ip' => request()->ip(),
                'device' => request()->userAgent(),
                'country' => $location['country'],
                'city' => $location['city'],
            ]
        );

        ActivityLog::create([
            'team_id' => $invitation->team_id,
            'user_id' => $user->id,
            'database_id' => null,
            'action' => "Joined team via invitation from {$invitation->inviter->name} ({$invitation->inviter->email})",
        ]);
    }

    public function updateTeamMemberRole(Request $request, Team $team, User $user)
    {
        try {
            $validated = $request->validate([
                'role' => ['required', 'in:super-admin,team-manager,database-maintainer,member']
            ]);

            // Update team membership pivot
            $team->members()->updateExistingPivot($user->id, [
                'permission_level' => $validated['role']
            ]);

            // Clear cached permissions
            cache()->forget("user-{$user->id}-permissions");

            // Refresh team data in session
            Team::setTeamDatabases($user->id, $team->id);

            $location = get_ip_location(request()->ip());

            log_user_activity(
                auth()->user(),
                ActivityType::TEAM_MEMBER_UPDATE,
                "Team member {$user->name} role updated from " . request()->ip(),
                [
                    'ip' => request()->ip(),
                    'device' => request()->userAgent(),
                    'country' => $location['country'],
                    'city' => $location['city'],
                ]
            );

            return redirect()->back()->with('success', 'Role updated successfully.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
