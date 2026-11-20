<?php

declare(strict_types=1);

namespace Libsql3\Tests;

use Libsql3\Internal\HttpTransport;

final class HttpTransportTest extends Libsql3TestCase
{
    public function test_fake_receives_method_url_payload_and_merged_headers(): void
    {
        $seen = [];
        HttpTransport::fake(function (string $url, string $method, ?array $payload, array $headers) use (&$seen): array {
            $seen = compact('url', 'method', 'payload', 'headers');

            return ['status' => 200, 'body' => [], 'raw' => '{}'];
        });

        $result = http_request(
            '/api/cli/login',
            'POST',
            ['username' => 'tester', 'password' => 'secret'],
            ['Authorization: Bearer test-token']
        );

        $this->assertSame(200, $result['status']);
        $this->assertSame('https://example.invalid/api/cli/login', $seen['url']);
        $this->assertSame('POST', $seen['method']);
        $this->assertSame(['username' => 'tester', 'password' => 'secret'], $seen['payload']);
        $this->assertContains('Authorization: Bearer test-token', $seen['headers']);
        $this->assertContains('Content-Type: application/json', $seen['headers']);
        $this->assertContains('X-Request-Source: CLI', $seen['headers']);
    }

    public function test_get_payload_is_encoded_into_the_query_string(): void
    {
        $seenUrl = '';
        HttpTransport::fake(function (string $url) use (&$seenUrl): array {
            $seenUrl = $url;

            return ['status' => 200, 'body' => [], 'raw' => '{}'];
        });

        http_request('/api/cli/db/lists', 'GET', ['page' => 2, 'filter' => 'active']);

        $this->assertSame('https://example.invalid/api/cli/db/lists?page=2&filter=active', $seenUrl);
    }

    public function test_any_2xx_is_accepted_by_default(): void
    {
        HttpTransport::fake(fn (): array => ['status' => 201, 'body' => ['ok' => true], 'raw' => '{"ok":true}']);

        $result = try_request('https://example.invalid/api/cli/db/token/mint/demo', 'POST');

        $this->assertNotNull($result);
        $this->assertSame(201, $result['status']);
        $this->assertSame(['ok' => true], $result['body']);
    }

    public function test_non_2xx_is_reported_as_null(): void
    {
        HttpTransport::fake(fn (): array => ['status' => 404, 'body' => [], 'raw' => '{}']);

        $this->assertNull(try_request('https://example.invalid/api/cli/team/restore/7', 'POST'));
    }

    public function test_explicit_ok_codes_are_honoured(): void
    {
        HttpTransport::fake(fn (): array => ['status' => 404, 'body' => ['expected' => true], 'raw' => '{"expected":true}']);

        $result = try_request('https://example.invalid/api/cli/team/restore/7', 'POST', null, [], 404);

        $this->assertNotNull($result);
        $this->assertSame(404, $result['status']);
    }
}
