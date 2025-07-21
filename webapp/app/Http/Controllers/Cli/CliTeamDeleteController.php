<?php

namespace App\Http\Controllers\Cli;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\Request;

class CliTeamDeleteController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        try {
            $teamId = $request->route('team_id');

            $team = Team::find($teamId);

            if (!$team) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Team not found',
                    'data' => []
                ], 404);
            }

            $team->delete();

            return response()->json([
                'status' => 'success',
                'message' => 'Team deleted successfully',
                'data' => []
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
                'data' => []
            ], 500);
        }
    }
}
