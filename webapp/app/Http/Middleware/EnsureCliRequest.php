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

        return $next($request);
    }
}
