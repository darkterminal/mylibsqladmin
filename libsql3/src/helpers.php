<?php

if (!function_exists('use_database')) {
    function use_database(): PDO
    {
        $database = config('db_path');
        return new PDO("sqlite:$database");
    }
}

if (!function_exists('config_set')) {
    function config_set(string $key, $value): void
    {
        $db = use_database();
        $table = config('tables.config');

        // Check if key exists
        $stmt = $db->prepare("SELECT COUNT(*) FROM $table WHERE key = :key");
        $stmt->execute(['key' => $key]);
        $exists = $stmt->fetchColumn() > 0;

        if ($exists) {
            // Update existing value
            $stmt = $db->prepare("UPDATE $table SET value = :value WHERE key = :key");
        } else {
            // Insert new value
            $stmt = $db->prepare("INSERT INTO $table (key, value) VALUES (:key, :value)");
        }

        $stmt->execute([
            'key' => $key,
            'value' => $value
        ]);
    }
}

if (!function_exists('config_get')) {
    function config_get(string $key): ?string
    {
        $db = use_database();
        $stmt = $db->prepare("SELECT value FROM " . config('tables.config') . " WHERE key = :key");
        $stmt->execute(['key' => $key]);
        return $stmt->fetchColumn();
    }
}

if (!function_exists('config')) {
    function config(string $key): mixed
    {
        $config = include __DIR__ . '/config.php';
        return array_get($config, $key);
    }
}

if (!function_exists('array_get')) {
    function array_get(array $array, ?string $key, $default = null)
    {
        if ($key === null) {
            return $array;
        }

        // Handle wildcard notation
        if (strpos($key, '*') !== false) {
            $result = [];
            $segments = explode('.', $key);
            $current = $array;

            foreach ($segments as $segment) {
                if ($segment === '*') {
                    if (!is_array($current)) {
                        return $default;
                    }

                    foreach ($current as $item) {
                        $remaining = implode('.', array_slice($segments, 1));
                        $result = array_merge($result, array_get($item, $remaining, $default));
                    }

                    return $result;
                }

                if (!is_array($current) || !array_key_exists($segment, $current)) {
                    return $default;
                }

                $current = $current[$segment];
            }

            return $current;
        }

        // Standard dot notation handling
        foreach (explode('.', $key) as $segment) {
            if (!is_array($array) || !array_key_exists($segment, $array)) {
                return $default;
            }

            $array = $array[$segment];
        }

        return $array;
    }
}

if (!function_exists('http_request')) {
    /**
     * Send an HTTP request (GET, POST, PUT, PATCH, DELETE) with JSON payload and custom headers.
     *
     * @param string      $pathUrl  Endpoint path, e.g. '/api/cli/login'
     * @param string      $method   HTTP method (GET, POST, PUT, PATCH, DELETE). Defaults to GET.
     * @param array|null  $payload  Data to send (query string for GET, JSON body for others).
     * @param array       $headers  Extra headers to merge with defaults.
     * @param int|int[]   $okCodes  Acceptable HTTP status code(s). Default: 200‑299.
     *
     * @return array{status:int, body:mixed, raw:string}
     * @throws Exception  On cURL error or unexpected HTTP status.
     */
    function http_request(
        string $pathUrl,
        string $method = 'GET',
        ?array $payload = null,
        array $headers = [],
        int|array $okCodes = 200,
    ): array {
        $endpoint = config_get('LIBSQL_API_ENDPOINT') ?? config('api_endpoint');
        $baseUrl = rtrim($endpoint, '/');
        $url = $baseUrl . '/' . ltrim($pathUrl, '/');

        $retries = 0;
        $maxRetries = 2;
        $response = null;

        $response = try_request($url, $method, $payload, $headers, $okCodes);

        if (str_starts_with($url, 'https://')) {
            do {
                $scheme = ($retries > 0) ? 'http' : 'https';
                $url = str_replace(['http://', 'https://'], $scheme . '://', $url);

                $response = try_request($url, $method, $payload, $headers, $okCodes);

                if ($response === null) {
                    $retries++;
                }
            } while ($retries < $maxRetries && $response === null);
        }

        if ($response === null) {
            throw new \Exception('Could not get a valid response after ' . $maxRetries . ' retries.');
        }

        return $response;
    }
}

if (!function_exists('try_request')) {
    /**
     * Try to send a request to the given URL with the given method and payload.
     * This function will retry the request up to $maxRetries times if the
     * response was invalid (e.g. due to a network issue or a server error).
     *
     * @param string $url          The URL to query.
     * @param string $method       The HTTP method to use. Default: GET.
     * @param array  $payload      The data to send with the request. Default: null.
     * @param array  $headers      Additional HTTP headers to send. Default: [].
     * @param int|array $okCodes   Acceptable HTTP status code(s). Default: 200.
     *
     * @return array{status:int, body:mixed, raw:string}|null  The response, or null if the request failed.
     */
    function try_request(
        string $url,
        string $method = 'GET',
        ?array $payload = null,
        array $headers = [],
        int|array $okCodes = 200
    ): ?array {
        $method = strtoupper($method);

        // Build payload
        $sendBody = null;
        $sendFields = null;

        if ($method === 'GET' && $payload) {
            // Encode as query string
            $url .= (str_contains($url, '?') ? '&' : '?') . http_build_query($payload);
        } elseif ($payload !== null) {
            // Encode as JSON for non‑GET methods
            $sendBody = json_encode($payload);
            $sendFields = $sendBody;
        }

        // Default headers (add Content‑Length only when sending a body)
        $defaultHeaders = [
            'Content-Type: application/json',
            'X-Request-Source: CLI',
        ];
        if ($sendBody !== null) {
            $defaultHeaders[] = 'Content-Length: ' . strlen($sendBody);
        }

        // Initialise cURL
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => array_merge($defaultHeaders, $headers),
        ]);

        // Attach body for non‑GET
        if ($sendFields !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $sendFields);
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        // Handle cURL‑level errors
        if ($curlError) {
            return null;
        }

        // Validate HTTP status code
        $okCodes = (array) $okCodes;
        if ($okCodes === [200]) {
            // Default: treat any 2xx as success
            $okCodes = range(200, 299);
        }
        if (!in_array($httpCode, $okCodes, true)) {
            return null;
        }

        // Attempt JSON‑decode; fallback to raw string
        $decoded = json_decode($response, true);
        $body = json_last_error() === JSON_ERROR_NONE ? $decoded : $response;

        return [
            'status' => $httpCode,
            'body' => $body,
            'raw' => $response,
        ];
    }
}
