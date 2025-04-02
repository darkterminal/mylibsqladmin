<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TeamAccess
{
    public function handle($request, Closure $next, ...$levels)
    {
        if (!$request->route()->hasParameter('team')) {
            return $next($request);
        }

        $team = $request->route('team');

        if (!$team) {
            abort(404, 'Team not found');
        }

        if (!$team->hasAccess(auth()->user(), $levels)) {
            abort(403, 'Insufficient team permissions');
        }

        return $next($request);
    }
}
