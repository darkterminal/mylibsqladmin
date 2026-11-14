<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class EnsureActiveUser
{
    /**
     * Block access for deactivated users and drop their stale web session.
     */
    public function handle(Request $request, Closure $next)
    {
        if ($request->user() && !$request->user()->is_active) {
            auth()->logout();

            $request->session()->flush();

            return redirect('/login')->with('error', 'Your account has been deactivated.');
        }

        return $next($request);
    }
}
