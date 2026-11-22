<?php

namespace Libsql3\Internal;

/**
 * The single HTTP boundary behind http_request()/try_request().
 *
 * Tests replace the real cURL path with self::fake() and restore it with
 * self::reset(). The fake receives the fully-built URL, the upper-cased method,
 * the original payload array and the merged header list, and must return the
 * same {status, body, raw} shape as the real path.
 */
class HttpTransport
{
    private static ?\Closure $fake = null;
    private static ?string $lastError = null;

    public static function fake(callable $fn): void
    {
        self::$fake = \Closure::fromCallable($fn);
    }

    public static function reset(): void
    {
        self::$fake = null;
        self::$lastError = null;
    }

    /**
     * cURL error from the most recent send(), or null when the last attempt
     * succeeded at the transport level (or was served by a fake).
     */
    public static function lastError(): ?string
    {
        return self::$lastError;
    }

    /**
     * Null means a transport-level failure (cURL error); try_request() maps it
     * to null, and http_request() includes lastError() in the final exception.
     *
     * @return array{status:int, body:mixed, raw:string}|null
     */
    public function send(string $url, string $method, ?array $payload, array $headers): ?array
    {
        self::$lastError = null;

        $method = strtoupper($method);

        $requestUrl = $url;
        $sendBody = null;
        if ($method === 'GET' && $payload) {
            $requestUrl = $url . (str_contains($url, '?') ? '&' : '?') . http_build_query($payload);
        } elseif ($payload !== null) {
            $sendBody = json_encode($payload);
        }

        $defaultHeaders = [
            'Content-Type: application/json',
            'X-Request-Source: CLI',
        ];
        if ($sendBody !== null) {
            $defaultHeaders[] = 'Content-Length: ' . strlen($sendBody);
        }
        $allHeaders = array_merge($defaultHeaders, $headers);

        if (self::$fake !== null) {
            return (self::$fake)($requestUrl, $method, $payload, $allHeaders);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $requestUrl,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $allHeaders,
        ]);

        if ($sendBody !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $sendBody);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            self::$lastError = $curlError;

            return null;
        }

        $decoded = json_decode($response, true);

        return [
            'status' => $httpCode,
            'body' => json_last_error() === JSON_ERROR_NONE ? $decoded : $response,
            'raw' => $response,
        ];
    }
}
