<?php

declare(strict_types=1);

namespace Libsql3\Tests;

use Libsql3\Internal\Endpoint;
use Libsql3\Internal\HttpTransport;
use PDO;

final class EndpointTest extends Libsql3TestCase
{
    public function test_https_is_allowed_for_any_host(): void
    {
        $endpoint = Endpoint::parse('https://db.example.invalid:8080', 'LIBSQL_DATABASE_ENDPOINT');

        $this->assertTrue($endpoint->isSecure());
        $this->assertSame('db.example.invalid', $endpoint->host);
        $this->assertSame(8080, $endpoint->port);
    }

    public function test_loopback_http_is_allowed_including_ipv6(): void
    {
        foreach (['http://localhost:8080', 'http://127.0.0.1:8080', 'http://[::1]:8080'] as $raw) {
            $endpoint = Endpoint::parse($raw, 'LIBSQL_DATABASE_ENDPOINT');
            $this->assertFalse($endpoint->isSecure());
            $this->assertContains($endpoint->host, ['localhost', '127.0.0.1', '::1']);
        }
    }

    public function test_remote_http_and_unsupported_schemes_are_rejected(): void
    {
        foreach (['http://192.0.2.10:8080', 'http://db.example.invalid'] as $raw) {
            try {
                Endpoint::parse($raw, 'LIBSQL_DATABASE_ENDPOINT');
                $this->fail("Expected {$raw} to be rejected");
            } catch (\RuntimeException $e) {
                $this->assertStringContainsString('plain HTTP is only allowed for loopback hosts', $e->getMessage());
            }
        }

        $this->expectException(\RuntimeException::class);
        Endpoint::parse('ftp://db.example.invalid', 'LIBSQL_DATABASE_ENDPOINT');
    }

    public function test_missing_endpoint_and_invalid_port_are_rejected(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('LIBSQL_DATABASE_ENDPOINT is not configured');
        Endpoint::parse('', 'LIBSQL_DATABASE_ENDPOINT');
    }

    public function test_remote_http_is_rejected_on_write(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('plain HTTP is only allowed for loopback hosts');
        config_set('LIBSQL_DATABASE_ENDPOINT', 'http://db.example.invalid:8080');
    }

    public function test_https_to_http_downgrade_is_rejected(): void
    {
        config_set('LIBSQL_DATABASE_ENDPOINT', 'https://db.example.invalid');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Refusing to downgrade LIBSQL_DATABASE_ENDPOINT');
        config_set('LIBSQL_DATABASE_ENDPOINT', 'http://localhost:8080');
    }

    public function test_http_request_rejects_a_stored_remote_http_endpoint(): void
    {
        // Write past config_set() to mimic a legacy value installed before
        // the scheme policy existed; the read path must still fail closed.
        $pdo = new PDO('sqlite:' . config('db_path'));
        $table = config('tables.config');
        $pdo->exec(sprintf(
            "UPDATE $table SET value = %s WHERE key = 'LIBSQL_API_ENDPOINT'",
            $pdo->quote('http://192.0.2.10:8000')
        ));

        HttpTransport::fake(fn (): array => ['status' => 200, 'body' => [], 'raw' => '{}']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('plain HTTP is only allowed for loopback hosts');
        http_request('/api/cli/team/lists');
    }

    public function test_database_name_accepts_safe_labels_only(): void
    {
        Endpoint::assertValidDatabaseName('my_db-1');

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Invalid database name');
        Endpoint::assertValidDatabaseName('bad name; echo pwned');
    }
}
