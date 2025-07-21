<?php

namespace App\Http\Controllers\Cli;

use App\Http\Controllers\Controller;
use App\Models\GroupDatabase;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CliGroupDeleteController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, $groupId)
    {
        try {
            $userIdentifier = $request->header('X-User-Identifier');

            $userId = User::where('username', $userIdentifier)->first()->id;

            logger()->debug('Deleting group: ', [
                'group_id' => $groupId,
                'user_id' => $userId,
                'user_identifier' => $userIdentifier
            ]);

            $group = GroupDatabase::with(['members', 'tokens'])
                ->where('user_id', $userId)
                ->where('id', $groupId)
                ->firstOrFail();

            DB::transaction(function () use ($group) {
                $group->members()->sync([]);
                $group->tokens()->delete();
                $group->delete();
            });

            return response()->json([
                'status' => true,
                'message' => 'Group deleted successfully',
                'data' => null
            ], 200);
        } catch (\Throwable $th) {
            return response()->json([
                'status' => false,
                'message' => $th->getMessage(),
                'data' => null
            ], 500);
        }
    }
}
