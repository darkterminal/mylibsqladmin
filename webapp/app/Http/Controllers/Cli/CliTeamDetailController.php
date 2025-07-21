<?php

namespace App\Http\Controllers\Cli;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\Request;

class CliTeamDetailController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $teamId = $request->route('team_id');

        $team = Team::find($teamId);

        if (!$team) {
            return response()->json([
                'status' => 'error',
                'message' => 'Team not found',
                'data' => []
            ], 404);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Team details fetched successfully',
            'data' => [
                'team' => $team
            ]
        ]);
    }
}
