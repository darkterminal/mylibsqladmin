<?php

namespace App\Http\Controllers;

use App\ActivityType;
use App\Events\TeamDatabasesRequested;
use App\Models\GroupDatabase;
use App\Models\Invitation;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use App\Http\Controllers\Concerns\RespondsWithInternalError;
use App\Services\TeamDashboardQuery;
use App\Services\TeamMembershipService;
use Inertia\Inertia;

class TeamController extends Controller
{
    use RespondsWithInternalError;

    public function __construct(
        private TeamMembershipService $membership,
    ) {}

    public function index(Request $request)
    {
        $user = auth()->user();
        $userId = $user->id;
        $isCanManage = $user->hasRole('Super Admin') || $user->hasRole('Team Manager');
        $hasManageTeamsPermission = $user->hasPermission('manage-teams');
        $canViewAll = $isCanManage || $hasManageTeamsPermission;

        // Start building the query for teams
        $teamsQuery = Team::query();

        // Define the relationships to be loaded with conditional constraints
        $teamsQuery->with([
            'members',
            'invitations.inviter',
            // Conditionally load the 'groups.members' relationship
            'groups' => function ($groupQuery) use ($canViewAll, $userId) {
                // If the user can view everything, load all members of the group
                if ($canViewAll) {
                    $groupQuery->with([
                        'members' => function ($memberQuery) {
                            $memberQuery->with('latestActivity')
                                ->select('id', 'database_name', 'is_schema', 'user_id', 'created_at');
                        }
                    ]);
                } else {
                    // For regular users, only load groups where they have granted access
                    $groupQuery->whereHas('members.grant', function ($q) use ($userId) {
                        $q->where('user_id', $userId);
                    })->with([
                                // And within those groups, ONLY load the members (databases) granted to them
                                'members' => function ($memberQuery) use ($userId) {
                                $memberQuery->whereHas('grant', function ($q) use ($userId) {
                                    $q->where('user_id', $userId);
                                })->with('latestActivity')
                                    ->select('id', 'database_name', 'is_schema', 'user_id', 'created_at');
                            }
                            ]);
                }
            }
        ]);

        // Apply top-level query constraints based on user role
        if (!$isCanManage) {
            // Both team managers and regular members should only see teams they belong to.
            $teamsQuery->whereHas('members', fn($q) => $q->where('user_id', $userId));
        }

        // Execute the query
        $teams = $teamsQuery->get();

        // Map the results for the frontend
        $teamData = $teams->map(function ($team) use ($user, $canViewAll) {

            $databaseMemberCount = $team->groups->flatMap(function ($group) {
                return $group->members;
            })->count();

            $pendingInvitations = TeamDashboardQuery::pendingInvitations($team);

            $groups = TeamDashboardQuery::groups($team->groups);

            // Filter recent activity based on permissions
            $recentActivityQuery = $team->recentActivities();
            if (!$canViewAll) {
                $recentActivityQuery->where('user_id', $user->id);
            }
            $recentActivity = TeamDashboardQuery::recentActivities($recentActivityQuery->get());

            return [
                'id' => $team->id,
                'name' => $team->name,
                'description' => $team->description,
                'members' => $databaseMemberCount,
                'team_members' => TeamDashboardQuery::teamMembers($team),
                'pending_invitations' => $pendingInvitations,
                'groups' => $groups,
                'recentActivity' => $recentActivity
            ];
        });

        return Inertia::render('dashboard-team', [
            'teams' => $teamData
        ]);
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


            log_user_activity(
                auth()->user(),
                ActivityType::TEAM_CREATE,
                "Team {$team->name} created from {$request->ip()}",
                activity_request_context()
            );

            return redirect()->back()->with([
                'success' => 'Team created successfully',
                'newTeam' => $team->id
            ]);
        } catch (\Exception $e) {
            return $this->backWithInternalError();
        }
    }

    public function updateTeam(Request $request, Team $team)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:255',
        ]);

        try {
            $team->name = $validated['name'];
            $team->description = $validated['description'];
            $team->save();

            Team::refreshLatestTeamDatabases((int) auth()->id());


            log_user_activity(
                auth()->user(),
                ActivityType::TEAM_UPDATE,
                "Team {$team->name} updated from {$request->ip()}",
                activity_request_context()
            );

            return response()->json([
                'success' => true,
                'message' => 'Team updated successfully'
            ]);
        } catch (\Exception $e) {
            return $this->jsonNotFoundOrError($e);
        }
    }

    public function deleteTeam(Request $request, Team $team)
    {
        try {
            $teamName = $team->name;
            $team->delete();

            Team::refreshLatestTeamDatabases((int) auth()->id());


            log_user_activity(
                auth()->user(),
                ActivityType::TEAM_DELETE,
                "Team {$teamName} deleted from {$request->ip()}",
                activity_request_context()
            );

            return response()->json([
                'success' => true,
                'message' => "Team {$teamName} deleted successfully"
            ]);
        } catch (\Exception $e) {
            return $this->jsonNotFoundOrError($e);
        }
    }

    public function archiveTeam(Request $request)
    {
        $user = auth()->user();

        // Archived teams are visible to Super Admins and those with team delete/manage rights.
        if (!$user->hasRole('Super Admin')
            && !$user->hasPermission('delete-teams')
            && !$user->hasPermission('manage-teams')) {
            abort(403);
        }

        $teamsQuery = Team::onlyTrashed()->with([
            'members',
            'groups' => function ($query) {
                $query->onlyTrashed();
            },
            'invitations.inviter',
            'groups.members' => function ($query) {
                $query->onlyTrashed()->with('latestActivity')
                    ->select('id', 'database_name', 'is_schema', 'user_id', 'created_at');
            }
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

            $pendingInvitations = TeamDashboardQuery::pendingInvitations($team);

            return [
                'id' => $team->id,
                'name' => $team->name,
                'description' => $team->description,
                'members' => $team->members->count(),
                'team_members' => TeamDashboardQuery::teamMembers($team),
                'pending_invitations' => $pendingInvitations,
                'groups' => $canAccessDatabases ? TeamDashboardQuery::groups($team->groups) : [],
                'recentActivity' => $canAccessDatabases ? TeamDashboardQuery::recentActivities($team->recentActivities()->get()) : []
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

            $user = auth()->user();

            log_user_activity(
                $user,
                ActivityType::TEAM_MEMBER_DELETE,
                "Team member {$user->name} deleted from {$request->ip()}",
                activity_request_context()
            );

            return redirect()->back()->with('success', "Team {$team->name} restored successfully");
        } catch (\Exception $e) {
            return $this->backWithInternalError();
        }
    }

    public function deleteTeamMember(Request $request, Team $team, User $user)
    {
        try {
            $this->membership->detachMember($team, $user);

            return response()->json([
                'success' => true,
                'message' => 'Team member deleted successfully'
            ]);
        } catch (\Exception $e) {
            return $this->jsonNotFoundOrError($e);
        }
    }

    public function getDatabases(Request $request, $teamId)
    {
        try {

            // The FetchTeamDatabases listener performs the sync once; do not
            // call setTeamDatabases directly here as well.
            TeamDatabasesRequested::dispatch(auth()->id(), $teamId);

            return response()->json([
                'success' => true,
                'message' => 'Databases stored in session'
            ]);

        } catch (\Exception $e) {
            return $this->jsonNotFoundOrError($e);
        }
    }

    public function storeMember(Request $request, Team $team)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'permission_level' => 'required|in:super-admin,team-manager,database-maintener,member'
        ]);

        $this->membership->storeMember($team, (int) $request->user_id, $request->permission_level);

        return redirect()->back()->with('success', 'Member added successfully');
    }

    public function invite(Request $request, Team $team)
    {
        // Invite = member administration for a team the caller belongs to.
        // Super Admins can invite anywhere; members need manage-team-members.
        $isMember = \DB::table('team_user')
            ->where('team_id', $team->id)
            ->where('user_id', auth()->id())
            ->exists();
        abort_unless(
            auth()->user()->hasRole('Super Admin')
                || ($isMember && auth()->user()->hasPermission('manage-team-members')),
            403,
            'Unauthorized'
        );

        $validated = $request->validate([
            'name' => 'nullable|string|max:255',
            'email' => 'required|email',
            'role' => 'required|in:super-admin,team-manager,database-maintainer,member'
        ]);

        $this->membership->invite($team, $validated['name'] ?? null, $validated['email'], $validated['role']);

        return response()->json([
            'success' => true,
            'message' => 'Invitation sent successfully'
        ]);
    }

    public function acceptInvite(Request $request, $token)
    {
        $invitation = Invitation::where('token', $token)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        // An invitation may only be claimed by someone who owns the email it
        // was issued to. Authenticated users must match their session email;
        // unauthenticated users get the invitation associated with the email
        // on record, never with a different one.
        $invitedEmail = strtolower((string) $invitation->email);

        if (auth()->check()) {
            $currentEmail = strtolower((string) $request->user()->email);

            if ($currentEmail !== $invitedEmail) {
                return redirect()->route('dashboard.teams')
                    ->with('error', 'This invitation was issued to a different email address.');
            }

            $this->membership->acceptInvitation($invitation, $request->user());
            $invitation->delete();
            return redirect()->route('dashboard.teams')
                ->with('success', 'Successfully joined the team');
        }

        // Handle existing users not logged in
        if ($user = User::where('email', $invitation->email)->first()) {
            $this->membership->acceptInvitation($invitation, $user);
            $invitation->delete();
            return redirect()->route('login')
                ->with('status', 'Please login to access the team');
        }

        // Store invitation for registration flow
        session()->put('valid_invitation', $invitation);
        return redirect()->route('register')
            ->with('info', 'Finish registration to join the team');
    }


    public function revokeInvite(Invitation $invitation)
    {
        if (!auth()->user()->can('delete', $invitation->team)) {
            abort(403);
        }

        $this->membership->revokeInvitation($invitation);

        return response()->json([
            'success' => true,
            'message' => 'Invitation revoked successfully',
        ]);
    }

    public function updateTeamMemberRole(Request $request, Team $team, User $user)
    {
        try {
            $validated = $request->validate([
                'role' => ['required', 'in:super-admin,team-manager,database-maintainer,member']
            ]);

            $this->membership->updateMemberRole($team, $user, $validated['role']);

            return redirect()->back()->with('success', 'Role updated successfully.');
        } catch (\Exception $e) {
            return $this->backWithInternalError();
        }
    }
}
