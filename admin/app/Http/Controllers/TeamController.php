<?php

namespace App\Http\Controllers;

use App\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;

class TeamController extends Controller
{
    public function index(Request $request)
    {
        $teams = Team::with([
            'members',
            'groups.members' => function ($query) {
                $query->with(['latestActivity', 'user'])
                    ->select('id', 'database_name', 'is_schema', 'user_id', 'created_at');
            },
            'recentActivities.user'
        ])->get();

        $teamData = $teams->map(fn($team) => [
            'id' => $team->id,
            'name' => $team->name,
            'description' => $team->description,
            'members' => $team->members->count(),
            'team_members' => $team->members->map(fn($member) => [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $member->pivot->role,
            ]),
            'groups' => $team->groups->map(fn($group) => [
                'id' => $group->id,
                'name' => $group->name,
                'databases' => $group->members->map(fn($database) => [
                    'id' => $database->id,
                    'name' => $database->database_name,
                    'type' => $this->determineDatabaseType($database->is_schema),
                    'lastActivity' => $database->latestActivity?->created_at->diffForHumans() ?? 'No activity'
                ])
            ]),
            'recentActivity' => $team->recentActivities->map(fn($activity) => [
                'id' => $activity->id,
                'user' => $activity->user->name,
                'action' => $activity->action,
                'database' => $activity->database->database_name,
                'time' => $activity->created_at->diffForHumans()
            ])
        ]);

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

    public function getDatabases(Request $request, $teamId)
    {
        try {

            Team::setTeamDatabases(auth()->id(), $teamId);
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
}
