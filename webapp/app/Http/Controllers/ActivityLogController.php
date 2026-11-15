<?php

namespace App\Http\Controllers;

use App\Models\UserDatabase;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;

class ActivityLogController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'team_id' => 'required|exists:teams,id',
            'database_name' => 'required|exists:user_databases,database_name',
            'query' => 'required|string|max:500'
        ]);

        $database = UserDatabase::where('database_name', $validated['database_name'])->first();
        $user = $request->user();

        $isSuperAdmin = $user->hasRole('Super Admin');
        $isTeamMember = \DB::table('team_user')
            ->where('team_id', $validated['team_id'])
            ->where('user_id', $user->id)
            ->exists();
        abort_unless($isSuperAdmin || $isTeamMember, 403, 'You are not a member of this team');

        $action = ActivityLogger::log(
            $validated['team_id'],
            $user->id,
            $database->id,
            $validated['query']
        );

        return response()->json([
            'success' => true,
            'message' => 'Activity logged successfully',
            'activity' => [
                'user' => $user->name,
                'action' => $action,
                'database' => $validated['database_name'],
                'time' => now()->diffForHumans()
            ]
        ], 201);
    }
}
