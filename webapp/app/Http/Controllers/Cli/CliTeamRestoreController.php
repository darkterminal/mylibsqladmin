<?php

namespace App\Http\Controllers\Cli;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\Request;

class CliTeamRestoreController extends Controller
{
    public function __invoke(Request $request, int $teamId)
    {
        $team = Team::withTrashed()->find($teamId);

        if (!$team) {
            return response()->json([
                'status' => false,
                'message' => 'Team not found',
                'data' => [],
            ], 404);
        }

        if (!$request->user()->can('restore', $team)) {
            return response()->json([
                'status' => false,
                'message' => 'Unauthorized',
                'data' => [],
            ], 403);
        }

        $team->restore();

        return response()->json([
            'status' => true,
            'message' => 'Team restored successfully',
            'data' => [
                'team' => [
                    'id' => $team->id,
                    'name' => $team->name,
                    'description' => $team->description,
                ],
            ],
        ], 200);
    }
}
