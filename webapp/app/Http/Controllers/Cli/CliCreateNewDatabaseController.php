<?php

namespace App\Http\Controllers\Cli;

use App\Http\Controllers\Controller;
use App\Models\GroupDatabase;
use App\Models\Team;
use App\Services\SqldService;
use Illuminate\Http\Request;

class CliCreateNewDatabaseController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string',
                'is_schema' => 'required',
                'group_id' => 'required|integer|exists:group_databases,id',
                'team_id' => 'required|integer|exists:teams,id'
            ]);

            SqldService::createDatabase(
                $validated['name'],
                $validated['is_schema'],
                $validated['group_id'],
                $validated['team_id'],
                'web'
            );

            $team = Team::find($validated['team_id'])->first('name');
            $group = GroupDatabase::find($validated['group_id'])->first('name');

            $data['id'] = rand(1, 9999);
            $data['name'] = $validated['name'];
            $data['team_id'] = $validated['team_id'];
            $data['group_id'] = $validated['group_id'];
            $data['is_schema'] = $validated['is_schema'];
            $data['team_name'] = $team->name;
            $data['group_name'] = $group->name;
            $data['created_at'] = now()->format('Y-m-d H:i:s');

            return response()->json([
                'status' => 'success',
                'message' => 'Database created successfully',
                'data' => [
                    'database' => $data
                ]
            ], 201);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => $th->getMessage(),
                'data' => []
            ], 500);
        }
    }
}
