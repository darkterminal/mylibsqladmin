<?php

namespace App\Http\Controllers\Cli;

use App\Http\Controllers\Controller;
use App\Models\UserDatabaseToken;
use Illuminate\Http\Request;

/**
 * GET /api/cli/db/token/{database_name}
 *
 * Never returns token material — only a fingerprint. A usable bearer
 * credential exists only at creation time; mint a fresh one via
 * POST /api/cli/db/token/mint/{database_name}.
 */
class CliGetTokenByDatabaseNameController extends Controller
{
    public function __invoke(Request $request, string $databaseName)
    {
        $databaseName = $request->route('database_name') ?? $request->input('database_name');

        $hash = $databaseName
            ? UserDatabaseToken::getTokenByDatabaseName($databaseName)
            : null;

        return response()->json([
            'status' => 'success',
            'message' => $hash ? 'A token exists for this database; it is never displayed.' : 'No token exists for this database.',
            'data' => [
                'token' => $hash ? '••••••••' . substr($hash, -8) : null,
                'mint_url' => url("/api/cli/db/token/mint/{$databaseName}"),
            ],
        ], 200);
    }
}
