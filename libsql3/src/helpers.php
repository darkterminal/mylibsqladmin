<?php

use Libsql3\Internal\Endpoint;
use Libsql3\Internal\HttpTransport;

if (!function_exists('use_database')) {
    function use_database(): PDO
    {
        $database = config('db_path');
        $pdo = new PDO("sqlite:$database");

        // The local store holds a reusable plaintext token. Tighten it on
        // every open so a permissive umask (or a legacy file) cannot leave it
        // readable by other users. Silence is intentional: an unsupported
        // chmod on a read-only mount must not break an otherwise valid login.
        @chmod(dirname($database), 0700);
        @chmod($database, 0600);

        return $pdo;
    }
}

if (!function_exists('initialize_local_store')) {
    /**
     * Create the local store schema and return a ready PDO connection.
     *
     * Global on purpose: App calls it before any command instance exists, and a
     * static method on the CliStore trait would collide with
     * Symfony\Component\Console\Command::initialize() on every command class.
     * Idempotent: CREATE TABLE IF NOT EXISTS makes repeated calls harmless.
     */
    function initialize_local_store(): PDO
    {
        // Tighten the directory even when it already exists with loose permissions.
        $dbDir = dirname(config('db_path'));
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0700, true);
        }
        @chmod($dbDir, 0700);

        try {
            $pdo = use_database();
            $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS " . config('tables.tokens') . " (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT NOT NULL,
                    token TEXT NOT NULL,
                    expires_at DATETIME NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");

            $pdo->exec("
                CREATE INDEX IF NOT EXISTS idx_username 
                ON " . config('tables.tokens') . " (username)
            ");

            $pdo->exec("
                CREATE TABLE IF NOT EXISTS " . config('tables.config') . " (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    key TEXT NOT NULL,
                    value TEXT NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");

            return $pdo;
        } catch (PDOException $e) {
            throw new \RuntimeException("Database initialization failed: " . $e->getMessage());
        }
    }
}

if (!function_exists('is_endpoint_config_key')) {
    function is_endpoint_config_key(string $key): bool
    {
        return $key === 'LIBSQL_API_ENDPOINT' || $key === 'LIBSQL_DATABASE_ENDPOINT';
    }
}

if (!function_exists('assert_endpoint_write')) {
    /**
     * Validate an endpoint write and refuse an HTTPS -> HTTP downgrade.
     */
    function assert_endpoint_write(string $key, string $value): void
    {
        // Parse first so malformed URLs fail before touching the store.
        Endpoint::parse($value, $key);

        $current = config_get($key);
        if ($current === null || trim($current) === '') {
            return;
        }

        try {
            $previous = Endpoint::parse($current, $key);
        } catch (\RuntimeException) {
            // A corrupt legacy value cannot defend itself; allow a clean write.
            return;
        }

        if ($previous->isSecure()) {
            throw new \RuntimeException(
                "Refusing to downgrade {$key} from HTTPS to plain HTTP; clear the stored value first"
            );
        }
    }
}

if (!function_exists('warn_if_insecure_endpoint')) {
    /**
     * Emit one visible warning per non-TLS endpoint value per process.
     */
    function warn_if_insecure_endpoint(string $url, string $context): void
    {
        static $warned = [];
        if (isset($warned[$url])) {
            return;
        }

        $endpoint = Endpoint::parse($url, $context);
        if ($endpoint->isSecure()) {
            return;
        }

        $warned[$url] = true;
        $message = "Warning: {$context} uses plain HTTP; credentials can be observed in transit. "
            . 'Use https:// for non-loopback deployments.';

        if (defined('STDERR')) {
            fwrite(STDERR, $message . PHP_EOL);
        } else {
            error_log($message);
        }
    }
}

if (!function_exists('config_set')) {
    function config_set(string $key, $value): void
    {
        if (is_endpoint_config_key($key)) {
            assert_endpoint_write($key, (string) $value);
        }

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

        $value = $stmt->fetchColumn();
        return $value === false ? null : (string) $value;
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
     * Retry policy: transport failures are retried for every method (nothing was
     * acknowledged by the server), and 5xx is retried only for safe methods.
     * A mutation is never replayed because of an HTTP status — its 4xx/5xx body
     * is either accepted through $okCodes or reported in the exception.
     *
     * @param string      $pathUrl  Endpoint path, e.g. '/api/cli/login'
     * @param string      $method   HTTP method (GET, POST, PUT, PATCH, DELETE). Defaults to GET.
     * @param array|null  $payload  Data to send (query string for GET, JSON body for others).
     * @param array       $headers  Extra headers to merge with defaults.
     * @param int|int[]   $okCodes  Acceptable HTTP status code(s). Default: 200‑299.
     *
     * @return array{status:int, body:mixed, raw:string}
     * @throws \RuntimeException  On transport failure or an unacceptable HTTP status.
     */
    function http_request(
        string $pathUrl,
        string $method = 'GET',
        ?array $payload = null,
        array $headers = [],
        int|array $okCodes = 200,
    ): array {
        // A missing row is null now; legacy stores may still hold "".
        // Treat both as unset so the env/config fallback stays reachable.
        $stored = config_get('LIBSQL_API_ENDPOINT');
        $endpoint = ($stored !== null && trim($stored) !== '') ? $stored : config('api_endpoint');

        if (!is_string($endpoint) || trim($endpoint) === '') {
            throw new \RuntimeException('LIBSQL_API_ENDPOINT is not configured');
        }

        $validated = Endpoint::parse($endpoint, 'LIBSQL_API_ENDPOINT');
        warn_if_insecure_endpoint($validated->value, 'LIBSQL_API_ENDPOINT');

        $url = $validated->value . '/' . ltrim($pathUrl, '/');

        $okCodes = (array) $okCodes;
        if ($okCodes === [200]) {
            // Default: treat any 2xx as success.
            $okCodes = range(200, 299);
        }

        $upperMethod = strtoupper($method);
        $safeMethod = $upperMethod === 'GET' || $upperMethod === 'HEAD';
        $maxAttempts = 3;
        $response = null;

        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            $response = try_request($url, $upperMethod, $payload, $headers);

            if ($response === null) {
                // No HTTP response arrived; safe to retry even for a mutation.
                if ($attempt < $maxAttempts) {
                    usleep(250000 * $attempt); // 250ms, 500ms backoff
                    continue;
                }

                $error = HttpTransport::lastError() ?: 'unknown transport error';
                throw new \RuntimeException(
                    "HTTP request failed after {$maxAttempts} attempt(s): {$error}"
                );
            }

            $status = (int) ($response['status'] ?? 0);
            if ($safeMethod && $status >= 500 && $status < 600 && $attempt < $maxAttempts) {
                usleep(250000 * $attempt);
                continue;
            }

            break;
        }

        if ($response === null) {
            // Defensive: the loop above either returns a response or throws.
            throw new \RuntimeException('HTTP request failed: no response');
        }

        $status = (int) ($response['status'] ?? 0);
        if (!in_array($status, $okCodes, true)) {
            $raw = (string) ($response['raw'] ?? '');
            $decoded = json_decode($raw, true);
            $message = is_array($decoded) ? ($decoded['message'] ?? null) : null;
            if (!is_string($message) || $message === '') {
                $message = $raw !== '' ? substr($raw, 0, 200) : 'Unexpected HTTP status';
            }
            throw new \RuntimeException("API returned status {$status}: {$message}");
        }

        return $response;
    }
}

if (!function_exists('revoke_cli_token')) {
    /**
     * Revoke a plaintext CLI token through the existing logout route.
     *
     * http_request() accepts any 2xx and throws with the server's message on a
     * non-2xx status, so callers only need to decide what to do with failure.
     */
    function revoke_cli_token(string $token): void
    {
        http_request('/api/cli/logout', 'POST', ['token' => $token]);
    }
}

if (!function_exists('try_request')) {
    /**
     * One HTTP attempt. Returns the parsed response for every HTTP status.
     * Null means a transport-level failure (no response arrived); status-code
     * acceptance is checked by http_request() so the body stays available.
     *
     * @param string $url      The URL to query.
     * @param string $method   The HTTP method to use. Default: GET.
     * @param array  $payload  The data to send with the request. Default: null.
     * @param array  $headers  Additional HTTP headers to send. Default: [].
     *
     * @return array{status:int, body:mixed, raw:string}|null
     */
    function try_request(
        string $url,
        string $method = 'GET',
        ?array $payload = null,
        array $headers = []
    ): ?array {
        // All cURL work lives in HttpTransport so tests can fake the boundary.
        return (new HttpTransport())->send($url, $method, $payload, $headers);
    }
}

if (!function_exists('parse_database_token_mint_response')) {
    /**
     * Normalize the plaintext response from /api/cli/db/token/mint.
     *
     * Both 200 and 201 are accepted because the platform mint endpoint has
     * returned 201 since v1.6.2. Callers keep their own exit codes; this helper
     * only decides whether a token was actually delivered.
     *
     * @param array{status:int, raw:string} $request
     * @return array{ok:bool, kind:?string, status:int, token:?string, fingerprint:?string, error:?string}
     */
    function parse_database_token_mint_response(array $request): array
    {
        $status = (int) ($request['status'] ?? 0);
        $raw = (string) ($request['raw'] ?? '');
        $decoded = json_decode($raw, true);
        $body = is_array($decoded) ? $decoded : [];

        if ($status !== 200 && $status !== 201) {
            $message = $body['message'] ?? null;
            $error = is_string($message) && $message !== ''
                ? $message
                : "Unexpected HTTP status {$status}";

            return [
                'ok' => false,
                'kind' => 'status',
                'status' => $status,
                'token' => null,
                'fingerprint' => null,
                'error' => $error,
            ];
        }

        $token = $body['data']['token'] ?? null;
        if (!is_string($token) || $token === '') {
            return [
                'ok' => false,
                'kind' => 'malformed',
                'status' => $status,
                'token' => null,
                'fingerprint' => null,
                'error' => 'Malformed response from server',
            ];
        }

        $fingerprint = $body['data']['fingerprint'] ?? null;

        return [
            'ok' => true,
            'kind' => null,
            'status' => $status,
            'token' => $token,
            'fingerprint' => is_string($fingerprint) ? $fingerprint : null,
            'error' => null,
        ];
    }
}
