<?php

namespace App\Http\Controllers\Cli;

use App\Http\Controllers\Controller;
use App\Models\Team;
use Illuminate\Http\Request;

class CliTeamListController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        return response()->json([
            'status' => 'success',
            'message' => 'Teams fetched successfully',
            'data' => [
                'teams' => Team::all()
            ]
        ]);
    }
}
