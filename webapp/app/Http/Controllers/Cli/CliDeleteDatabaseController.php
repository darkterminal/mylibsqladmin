<?php

namespace App\Http\Controllers\Cli;

use App\Http\Controllers\Controller;
use App\Models\UserDatabase;
use App\Services\SqldService;
use Illuminate\Http\Request;

class CliDeleteDatabaseController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, $databaseName)
    {
        $database = UserDatabase::where('database_name', $databaseName)->first();

        if (!$database) {
            return response()->json([
                'success' => false,
                'message' => 'Database not found',
                'data' => null,
            ], 404);
        }

        if (!auth()->user()->can('delete', $database)) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
                'data' => null,
            ], 403);
        }

        if (SqldService::archiveDatabase($databaseName, (int) $database->user_id, 'web')) {
            return response()->json([
                'success' => true,
                'message' => 'Database deleted successfully',
                'data' => null
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Database deletion failed',
            'data' => null
        ], 500);
    }
}
