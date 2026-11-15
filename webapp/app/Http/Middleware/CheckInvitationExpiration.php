<?php

namespace App\Http\Middleware;

use App\Models\Invitation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckInvitationExpiration
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->route('token');

        $invitation = $token ? Invitation::where('token', $token)->first() : null;

        if (!$invitation || $invitation->expires_at < now()) {
            return redirect()->route('invitation.expired');
        }

        return $next($request);
    }
}
