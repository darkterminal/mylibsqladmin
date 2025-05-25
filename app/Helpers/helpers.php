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

if (!function_exists('sanitizeData')) {
    function sanitizeData($data)
    {
        if (is_string($data)) {
            return mb_convert_encoding($data, 'UTF-8', 'UTF-8');
        }

        foreach ($data as $key => $value) {
            if (is_array($value)) {
                $data[$key] = sanitizeData($value);
            } elseif (is_string($value)) {
                $data[$key] = mb_convert_encoding($value, 'UTF-8', 'UTF-8');
            }
        }
        return $data;
    }
}
