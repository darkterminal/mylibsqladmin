<?php

namespace App\Http\Controllers\Cli;

use App\Http\Controllers\Controller;
use App\Models\UserDatabase;
use App\Models\UserDatabaseToken;
use App\Services\DatabaseTokenGenerator;
use Illuminate\Http\Request;

/**
 * POST /api/cli/db/token/mint/{database_name}
 *
 * Mints a fresh full-access token pair for the named database and returns
 * the plaintext once (storage is SHA-256 hashed, like every other token in
 * the platform). Replaces the previous cli-shell-token for this user and
 * database pair, so the CLI never accumulates stale credentials.
 */
class CliMintDatabaseTokenController extends Controller
{
    public function __invoke(Request $request, string $databaseName)
    {
        $database = UserDatabase::where('database_name', $databaseName)->first();

        if (!$database) {
            return response()->json([
                'status' => 'error',
                'message' => 'Database not found',
                'data' => [],
            ], 404);
        }

        $userId = $request->user()->id;

        // Replace any previous CLI-minted token for this pair — idempotent.
        UserDatabaseToken::where('user_id', $userId)
            ->where('database_id', $database->id)
            ->where('name', 'cli-shell-token')
            ->delete();

        $pair = (new DatabaseTokenGenerator())->generateToken($database->id, $userId, 0);

        UserDatabaseToken::create([
            'user_id' => $userId,
            'database_id' => $database->id,
            'name' => 'cli-shell-token',
            'full_access_token' => hash('sha256', $pair['full_access_token']),
            'read_only_token' => hash('sha256', $pair['read_only_token']),
            'expiration_day' => 0, // never expires, same contract as web "never" choice
            'created_by' => $userId,
        ]);

        return response()->json([
            'status' => 'success',
            'message' => 'Database token minted',
            'data' => [
                'token' => $pair['full_access_token'],
                'read_only_token' => $pair['read_only_token'],
                'fingerprint' => '••••••••' . substr((string) $pair['full_access_token'], -8),
                'database' => $databaseName,
                'expires_at' => null,
            ],
        ], 201);
    }
}
