<?php

namespace App\Http\Controllers\Cli;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\Request;

class CliTeamArchiveListController extends Controller
{
    public function __invoke(Request $request)
    {
        $teams = Team::onlyTrashed()
            ->get()
            ->filter(fn (Team $team) => $request->user()->can('restore', $team))
            ->map(fn (Team $team) => [
                'id' => $team->id,
                'name' => $team->name,
                'description' => $team->description,
                'deleted_at' => $team->deleted_at,
            ])
            ->values();

        return response()->json([
            'status' => true,
            'message' => 'Archived teams fetched successfully',
            'data' => ['teams' => $teams],
        ], 200);
    }
}
