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
