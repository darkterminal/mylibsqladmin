<?php

namespace App\Http\Controllers\Cli;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class CliTeamFinderController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        try {
            $teamName = $request->team_name;
            $team = \App\Models\Team::where('name', $teamName)->first();

            if (!$team) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'No team found',
                    'data' => []
                ], 404);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Team found successfully',
                'data' => [
                    'team' => $team
                ]
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
