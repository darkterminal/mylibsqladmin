<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class IpGeolocationService
{
    protected const CACHE_TTL = 86400; // 24 hours
    protected const API_ENDPOINT = 'http://ip-api.com/json/%s?fields=status,message,country,regionName,city,lat,lon,isp,query';

    public function getLocationData(string $ip): array
    {
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
            $response = Http::timeout(2)
                ->get(sprintf(self::API_ENDPOINT, $ip))
                ->json();

            return $this->parseResponse($response);
        } catch (\Exception $e) {
            return $this->defaultResponse();
        }
    }

    protected function parseResponse(array $response): array
    {
        if ($response['status'] !== 'success') {
            return $this->defaultResponse($response['message'] ?? 'Unknown error');
        }

        return [
            'country' => $response['country'] ?? 'Unknown',
            'region' => $response['regionName'] ?? 'Unknown',
            'city' => $response['city'] ?? 'Unknown',
            'coordinates' => [
                'lat' => $response['lat'] ?? null,
                'lon' => $response['lon'] ?? null,
            ],
            'isp' => $response['isp'] ?? 'Unknown',
            'ip' => $response['query'] ?? 'Unknown',
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
