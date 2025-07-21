<?php

namespace App\Http\Controllers\Cli;

use App\Http\Controllers\Controller;
use App\Services\SqldService;
use Illuminate\Http\Request;

class CliDeleteDatabaseController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, $databaseName)
    {
        if (SqldService::archiveDatabase($databaseName, 'web')) {
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
