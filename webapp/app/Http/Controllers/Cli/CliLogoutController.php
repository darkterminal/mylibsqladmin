<?php

namespace App\Http\Controllers\Cli;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;

class CliLogoutController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request)
    {
        // Validate required token
        $request->validate([
            'token' => 'required|string'
        ]);

        // Find token without truncating (Sanctum stores tokens with hash)
        $token = PersonalAccessToken::findToken($request->token);

        if (!$token) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invalid token',
                'data' => []
            ], 401);
        }

        // Verify token hasn't expired
        if ($token->expires_at && now()->gt($token->expires_at)) {
            return response()->json([
                'status' => 'error',
                'message' => 'Token already expired',
                'data' => []
            ], 401);
        }

        // Delete token
        $token->delete();

        return response()->json([
            'status' => 'success',
            'message' => 'Successfully logged out',
            'data' => [
                'revoked_token' => $request->token
            ]
        ]);
    }
}
