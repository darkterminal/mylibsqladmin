<?php

namespace App\Http\Middleware;

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

        // Identity comes from the Sanctum-authenticated guard, never from a
        // client-supplied header. A caller must be logged in AND hold the
        // Super Admin role to reach CLI admin routes.
        $user = $request->user();

        if (!$user) {
            return response()->json(['error' => 'Unauthenticated'], 401);
        }

        if (!$user->hasRole('Super Admin')) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return $next($request);
    }
}
