<?php

namespace App\Http\Controllers\Cli;

use App\Http\Controllers\Controller;
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

        if (SqldService::restoreDatabase($dbName, (int) auth()->id(), 'web')) {
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
