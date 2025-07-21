<?php

namespace App\Http\Controllers\Cli;

use App\Http\Controllers\Controller;
use App\Models\GroupDatabase;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CliGroupCreateController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        try {
            $validated = $request->validate([
                'team_id' => 'required|integer',
                'name' => 'required|string'
            ]);

            $userIdentifier = $request->header('X-User-Identifier');
            $userId = User::where('username', $userIdentifier)->first()->id;

            DB::transaction(function () use ($validated, $userId) {
                $group = GroupDatabase::create([
                    'name' => $validated['name'],
                    'user_id' => $userId,
                    'team_id' => $validated['team_id'],
                    'created_by' => $userId
                ]);

                return $group->load(['team', 'user:id,name'])
                    ->loadCount('members');
            });

            return response()->json([
                'status' => true,
                'message' => 'Group created successfully',
                'data' => [
                    'group' => [
                        'name' => $validated['name'],
                        'team_name' => Team::where('id', $validated['team_id'])->first()->name,
                        'created_at' => now()->format('Y-m-d H:i:s'),
                    ]
                ]
            ], 201);

        } catch (\Throwable $th) {
            return response()->json([
                'status' => true,
                'message' => $th->getMessage(),
                'data' => []
            ], 500);
        }
    }
}
