<?php

namespace App\Http\Controllers;

use App\Events\TeamDatabasesRequested;
use App\Jobs\SendTeamInvitation;
use App\Models\Invitation;
use App\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class TeamController extends Controller
{
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
                'permission_level' => 'super-admin'
            ]);

            TeamDatabasesRequested::dispatch(auth()->id(), $team->id);

            return redirect()->back()->with('success', 'Team created successfully');
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

        return redirect()->back()->with('success', 'Member added successfully');
    }

    public function invite(Request $request, Team $team)
    {
        $request->validate([
            'email' => 'required|email',
            'role' => 'required|in:super-admin,team-manager,database-maintener,member'
        ]);

        $invitation = $team->invitations()->create([
            'email' => $request->email,
            'token' => Str::random(64),
            'inviter_id' => auth()->id(),
            'permission_level' => $request->role,
            'expires_at' => now()->addDays(7)
        ]);

        // Send notification
        SendTeamInvitation::dispatch($invitation);

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

        if (auth()->guest()) {
            session()->put('valid_invitation', $invitation);
            return redirect()->route('register');
        }

        $invitation->team->members()->attach(auth()->id(), [
            'permission_level' => $invitation->permission_level
        ]);

        $invitation->delete();

        return redirect()->route('dashboard.teams')
            ->with('success', 'Joined team successfully');
    }
}
