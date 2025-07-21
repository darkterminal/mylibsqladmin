<?php

namespace App\Http\Controllers\Cli;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\Request;

class CliTeamEditController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, $teamId)
    {
        try {
            $validate = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:255',
            ]);

            $team = Team::find($teamId);

            $team->name = $validate['name'];
            $team->description = $validate['description'];
            $team->touch('updated_at');
            $team->save();

            return response()->json([
                'status' => 'success',
                'message' => 'Team updated successfully',
                'data' => [
                    'team' => [
                        'id' => $team->id,
                        'name' => $team->name,
                        'description' => $team->description,
                        'updated_at' => $team->updated_at->format('Y-m-d H:i:s'),
                    ],
                ],
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
                'data' => [],
            ]);
        }
    }
}
