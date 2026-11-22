<?php

namespace App\Http\Controllers\Cli;

use App\Http\Controllers\Controller;
use App\Models\UserDatabase;
use App\Services\SqldService;
use Illuminate\Http\Request;

class CliResotreDatabaseController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        $dbName = $request->route('database_name') ?? $request->input('database_name');

        $database = UserDatabase::withTrashed()->where('database_name', $dbName)->first();

        if (!$database) {
            return response()->json([
                'success' => false,
                'message' => 'Database not found',
                'data' => null,
            ], 404);
        }

        if (!auth()->user()->can('restore', $database)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
                'data' => null,
            ], 403);
        }

        if (SqldService::restoreDatabase($dbName, (int) $database->user_id, 'web')) {
            return response()->json([
                'success' => true,
                'message' => 'Database restored successfully',
                'data' => null
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Database restore failed',
            'data' => null
        ], 500);
    }
}
