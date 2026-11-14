<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class IpGeolocationService
{
    protected const CACHE_TTL = 86400; // 24 hours

    protected const API_ENDPOINT = 'https://ipwho.is/%s?fields=success,country,region,city,latitude,longitude,connection,ip,message';

    /**
     * Resolve the location of an IP via ipwho.is (HTTPS, no API key).
     *
     * Geolocation is DISABLED by default — set GEOLOCATION_ENABLED=true
     * in env to opt in. Disabling means no IP ever leaves the server.
     */
    public function getLocationData(string $ip): array
    {
        if (!filter_var(env('GEOLOCATION_ENABLED', false), FILTER_VALIDATE_BOOLEAN)) {
            return $this->defaultResponse('Geolocation disabled');
        }

        if (!$this->isValidIp($ip)) {
            return $this->defaultResponse();
        }

        return Cache::remember("ip-geo-{$ip}", self::CACHE_TTL, function () use ($ip) {
            return $this->fetchFromApi($ip);
        });
    }

    protected function fetchFromApi(string $ip): array
    {
        try {
            $response = Http::timeout(3)
                ->get(sprintf(self::API_ENDPOINT, $ip))
                ->json();

            return $this->parseResponse(is_array($response) ? $response : []);
        } catch (\Throwable) {
            return $this->defaultResponse('Lookup failed');
        }
    }

    protected function parseResponse(array $response): array
    {
        if (($response['success'] ?? false) !== true) {
            return $this->defaultResponse($response['message'] ?? 'Unknown error');
        }

        $connection = $response['connection'] ?? [];

        return [
            'country' => $response['country'] ?? 'Unknown',
            'region' => $response['region'] ?? 'Unknown',
            'city' => $response['city'] ?? 'Unknown',
            'coordinates' => [
                'lat' => $response['latitude'] ?? null,
                'lon' => $response['longitude'] ?? null,
            ],
            'isp' => is_array($connection) ? ($connection['isp'] ?? 'Unknown') : 'Unknown',
            'ip' => $response['ip'] ?? 'Unknown',
            'success' => true,
        ];
    }

    protected function isValidIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP) !== false;
    }

    protected function defaultResponse(string $error = 'Invalid IP address'): array
    {
        return [
            'country' => 'Unknown',
            'region' => 'Unknown',
            'city' => 'Unknown',
            'coordinates' => ['lat' => null, 'lon' => null],
            'isp' => 'Unknown',
            'ip' => 'Unknown',
            'error' => $error,
            'success' => false,
        ];
    }
}
