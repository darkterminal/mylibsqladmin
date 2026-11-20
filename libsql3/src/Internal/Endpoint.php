<?php

declare(strict_types=1);

namespace Libsql3\Internal;

/**
 * Validated CLI endpoint.
 *
 * This is the single place that decides which endpoint URLs the CLI accepts.
 * SEC-15 is sealed server-side but had no CLI implementation: HTTPS is always
 * allowed, plain HTTP only for loopback hosts.
 */
final class Endpoint
{
    private const LOOPBACK_HOSTS = ['localhost', '127.0.0.1', '::1'];

    private function __construct(
        public readonly string $value,
        public readonly string $scheme,
        public readonly string $host,
        public readonly ?int $port,
    ) {
    }

    public static function parse(string $raw, string $key = 'endpoint'): self
    {
        if (trim($raw) === '') {
            throw new \RuntimeException("{$key} is not configured");
        }

        if (filter_var($raw, FILTER_VALIDATE_URL) === false) {
            throw new \RuntimeException("Invalid {$key}: expected an absolute URL");
        }

        $parts = parse_url($raw);
        if (!is_array($parts)) {
            throw new \RuntimeException("Invalid {$key}: could not parse URL");
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        // parse_url() keeps the brackets around IPv6 literals.
        $host = trim(strtolower((string) ($parts['host'] ?? '')), '[]');

        if ($scheme === '' || $host === '') {
            throw new \RuntimeException("Invalid {$key}: scheme and host are required");
        }

        if ($scheme !== 'http' && $scheme !== 'https') {
            throw new \RuntimeException("Invalid {$key}: unsupported scheme '{$scheme}'");
        }

        if ($scheme === 'http' && !in_array($host, self::LOOPBACK_HOSTS, true)) {
            throw new \RuntimeException(
                "Invalid {$key}: plain HTTP is only allowed for loopback hosts; use https://"
            );
        }

        $port = null;
        if (array_key_exists('port', $parts)) {
            $port = filter_var($parts['port'], FILTER_VALIDATE_INT);
            if ($port === false || $port < 1 || $port > 65535) {
                throw new \RuntimeException("Invalid {$key}: port must be an integer between 1 and 65535");
            }
        }

        return new self(rtrim($raw, '/'), $scheme, $host, $port);
    }

    public function isSecure(): bool
    {
        return $this->scheme === 'https';
    }

    public static function assertValidDatabaseName(string $name): void
    {
        if (preg_match('/\A[A-Za-z0-9_-]+\z/D', $name) !== 1) {
            throw new \RuntimeException(
                'Invalid database name: only letters, digits, underscore and hyphen are allowed'
            );
        }
    }
}
