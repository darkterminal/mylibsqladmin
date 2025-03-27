<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TeamAccess
{
    public function handle($request, $next, $requiredLevel)
    {
        $team = $request->route('team');

        if (!$team->hasAccess(auth()->user(), $requiredLevel)) {
            abort(403, 'Insufficient team permissions');
        }

        return $next($request);
    }
}
