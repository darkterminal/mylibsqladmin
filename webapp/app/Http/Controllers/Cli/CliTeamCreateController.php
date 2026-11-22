<?php

namespace App\Http\Controllers\Cli;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CliTeamCreateController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        try {
            // Identity comes from the Sanctum session, not from client headers.
            $user = $request->user();

            $validated = $request->validate([
                'name' => 'required|string|max:255',
                'description' => 'nullable|string|max:255',
            ]);

            $team = DB::transaction(function () use ($user, $validated) {
                $team = $user->teams()->create([
                    'name' => $validated['name'],
                    'description' => $validated['description'],
                ]);

                // belongsToMany::create() already inserts the pivot row. Attaching
                // again violates UNIQUE(team_id, user_id) and used to leave an
                // orphan team behind because there was no transaction (F-50).
                $team->members()->updateExistingPivot($user->id, [
                    'permission_level' => Role::getRoleKey($user->getRoleAttribute())
                ]);

                return $team;
            });

            return response()->json([
                'status' => 'success',
                'message' => 'Team created successfully',
                'data' => [
                    'team' => [
                        'id' => $team->id,
                        'name' => $team->name,
                        'description' => $team->description,
                        'created_at' => $team->created_at->format('Y-m-d H:i:s'),
                        'updated_at' => $team->updated_at->format('Y-m-d H:i:s'),
                    ]
                ]
            ]);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => 'error',
                'message' => 'Internal server error',
                'data' => []
            ], 500);
        }
    }
}
