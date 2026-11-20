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

    public function test_non_2xx_response_is_returned_with_its_body(): void
    {
        HttpTransport::fake(fn (): array => [
            'status' => 422,
            'body' => ['message' => 'invalid group'],
            'raw' => '{"message":"invalid group"}',
        ]);

        $result = try_request('https://example.invalid/api/cli/team/restore/7', 'POST');

        $this->assertNotNull($result);
        $this->assertSame(422, $result['status']);
        $this->assertSame('invalid group', $result['body']['message']);
    }

    public function test_explicit_ok_codes_are_honoured_by_http_request(): void
    {
        $calls = 0;
        HttpTransport::fake(function () use (&$calls): array {
            $calls++;

            return ['status' => 404, 'body' => ['expected' => true], 'raw' => '{"expected":true}'];
        });

        $result = http_request('/api/cli/team/restore/7', 'POST', null, [], 404);

        $this->assertSame(1, $calls);
        $this->assertSame(404, $result['status']);
    }

    public function test_unacceptable_status_throws_once_with_the_server_message(): void
    {
        $calls = 0;
        HttpTransport::fake(function () use (&$calls): array {
            $calls++;

            return [
                'status' => 422,
                'body' => [],
                'raw' => json_encode(['message' => 'The group_id field is invalid.']),
            ];
        });

        try {
            http_request('/api/cli/db/create', 'POST', ['name' => 'demo']);
            $this->fail('An unacceptable status must throw.');
        } catch (\RuntimeException $e) {
            $this->assertSame(1, $calls, 'A mutation must not be replayed because of an HTTP status.');
            $this->assertSame('API returned status 422: The group_id field is invalid.', $e->getMessage());
        }
    }

    public function test_transport_failure_is_retried_without_replaying_a_status(): void
    {
        $calls = 0;
        HttpTransport::fake(function () use (&$calls): ?array {
            $calls++;

            return null;
        });

        try {
            http_request('/api/cli/db/create', 'POST', ['name' => 'demo']);
            $this->fail('Exhausted transport retries must throw.');
        } catch (\RuntimeException $e) {
            $this->assertSame(3, $calls);
            $this->assertSame(
                'HTTP request failed after 3 attempt(s): unknown transport error',
                $e->getMessage()
            );
        }
    }

    public function test_server_error_on_get_is_retried_but_not_on_post(): void
    {
        $getCalls = 0;
        HttpTransport::fake(function () use (&$getCalls): array {
            $getCalls++;

            return ['status' => 503, 'body' => [], 'raw' => '{"message":"busy"}'];
        });

        try {
            http_request('/api/cli/db/lists', 'GET');
            $this->fail('An exhausted 5xx GET must throw.');
        } catch (\RuntimeException $e) {
            $this->assertSame(3, $getCalls);
            $this->assertSame('API returned status 503: busy', $e->getMessage());
        }

        $postCalls = 0;
        HttpTransport::fake(function () use (&$postCalls): array {
            $postCalls++;

            return ['status' => 503, 'body' => [], 'raw' => '{"message":"busy"}'];
        });

        try {
            http_request('/api/cli/db/create', 'POST', ['name' => 'demo']);
            $this->fail('An unacceptable status must throw.');
        } catch (\RuntimeException $e) {
            $this->assertSame(1, $postCalls, 'A mutation must never be replayed on 5xx.');
        }
    }
}
