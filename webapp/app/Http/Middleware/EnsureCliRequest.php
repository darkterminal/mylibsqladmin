<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;

class EnsureCliRequest
{
    public function handle(Request $request, Closure $next)
    {
        // Check for CLI request header
        if ($request->header('X-Request-Source') !== 'CLI') {
            return response()->json([
                'error' => 'This endpoint is only accessible from the CLI application'
            ], 403);
        }

        // Get user identifier from header
        $userIdentifier = $request->header('X-User-Identifier');

        // Find requesting user
        $requestingUser = User::where('username', $userIdentifier)
            ->orWhere('id', $userIdentifier)->first();

        if (!$requestingUser->exists()) {
            return response()->json(['error' => 'User not found'], 404);
        }

        logger()->debug("Role of requesting user is superadmin: " . $requestingUser->hasRole('Super Admin'));

        // Verify user is superadmin
        if (!$requestingUser->hasRole('Super Admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return $next($request);
    }
}
