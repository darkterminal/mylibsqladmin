<?php

namespace App\Http\Controllers\Settings;

use App\ActivityType;
use App\Http\Controllers\Controller;
use App\Services\SystemSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class GeneralController extends Controller
{
    /**
     * Show the instance-wide general settings page (Super Admin only).
     *
     * Authorization is enforced here on the server; UI visibility is never
     * treated as access control.
     */
    public function edit(Request $request): Response
    {
        abort_unless($request->user()?->hasRole('Super Admin'), 403);

        return Inertia::render('settings/general', [
            'effectiveTimezone' => SystemSettings::timezone(),
            'timezoneSource' => SystemSettings::timezoneSource(),
            'envTimezone' => env('APP_TIMEZONE') ?: null,
            'availableTimezones' => timezone_identifiers_list(),
        ]);
    }

    /**
     * Persist the instance-wide timezone (Super Admin only).
     */
    public function update(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('Super Admin'), 403);

        $validated = $request->validate([
            'timezone' => [
                'required',
                'string',
                'max:64',
                Rule::in(timezone_identifiers_list()),
            ],
        ]);

        SystemSettings::storeTimezone($validated['timezone']);

        log_user_activity(
            Auth::user(),
            ActivityType::SETTINGS_UPDATE,
            "General settings: timezone set to {$validated['timezone']} from {$request->ip()}",
            activity_request_context()
        );

        return to_route('general.settings.edit')->with('success', 'General settings updated.');
    }
}
