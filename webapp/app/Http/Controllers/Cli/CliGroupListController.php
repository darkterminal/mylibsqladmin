<?php

namespace App\Http\Controllers\Cli;

use App\Http\Controllers\Controller;
use App\Models\GroupDatabase;
use Illuminate\Http\Request;

class CliGroupListController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $groups = GroupDatabase::select('id', 'name', 'created_at', 'updated_at')
            ->with([
                'members' => function ($query) {
                    $query->select('user_databases.id', 'database_name');
                }
            ])
            ->get();

        return response()->json([
            'status' => 'success',
            'message' => 'Groups fetched successfully',
            'data' => [
                'groups' => $groups->map(fn($group) => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'members_count' => $group->members->count(),
                    'created_at' => $group->created_at->format('Y-m-d H:i:s'),
                    'updated_at' => $group->updated_at->format('Y-m-d H:i:s'),
                ]),
            ]
        ], 200);
    }
}
