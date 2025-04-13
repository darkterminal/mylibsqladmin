<?php

namespace App\Http\Controllers\Settings;

use App\ActivityType;
use App\Http\Controllers\Controller;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

class PasswordController extends Controller
{
    /**
     * Show the user's password settings page.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/password', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => $request->session()->get('status'),
        ]);
    }

    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $location = get_ip_location($request->ip());

        log_user_activity(
            Auth::user(),
            ActivityType::PASSWORD_UPDATE,
            "Password change from {$request->ip()}",
            [
                'ip' => $request->ip(),
                'device' => $request->userAgent(),
                'country' => $location['country'],
                'city' => $location['city'],
            ]
        );

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        return back();
    }
}
