<?php

use App\ActivityType;
use App\Models\User;
use App\Services\IpGeolocationService;
use App\Services\UserActivityLogger;
use Illuminate\Contracts\Auth\Authenticatable;

if (!function_exists('log_user_activity')) {
    function log_user_activity(
        User|Authenticatable $user,
        ActivityType $type,
        string $description,
        ?array $metadata = null
    ): void {
        UserActivityLogger::record($user, $type->value, $description, $metadata);
    }
}

if (!function_exists('get_ip_location')) {
    function get_ip_location($ip)
    {
        $geoService = new IpGeolocationService();
        $location = $geoService->getLocationData($ip);

        return $location;
    }
}

if (!function_exists('activity_request_context')) {
    /**
     * Build the standard audit-trail metadata for the current HTTP request
     * (geo lookup + user agent). Pass as the $metadata argument to
     * log_user_activity() instead of duplicating this at every call site.
     */
    function activity_request_context(?\Illuminate\Http\Request $request = null): array
    {
        $request ??= request();
        $location = get_ip_location($request->ip());

        $context = [
            'ip' => $request->ip(),
            'device' => $request->userAgent(),
            'country' => $location['country'] ?? 'Unknown',
            'city' => $location['city'] ?? 'Unknown',
        ];

        foreach (['region', 'coordinates', 'isp'] as $key) {
            if (isset($location[$key])) {
                $context[$key] = $location[$key];
            }
        }

        return $context;
    }
}

if (!function_exists('mylibsqladmin_env')) {
    function mylibsqladmin_env(string $variable_name): string
    {
        return in_array(php_sapi_name(), ['cli']) && env('APP_ENV') === 'production' ? env($variable_name, 'db') : 'localhost';
    }
}
