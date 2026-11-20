<?php

declare(strict_types=1);

namespace Libsql3\Tests;

use Libsql3\Cmd\AuthLogout;
use Libsql3\Internal\AuthContext;
use Libsql3\Internal\CliStore;
use Libsql3\Internal\HttpTransport;
use PDO;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Credential-lifetime tests for the CLI auth path. Every case uses the fake
 * transport, so no request can escape to a real endpoint.
 */
final class AuthTest extends Libsql3TestCase
{
    public function test_logout_on_200_clears_the_local_token(): void
    {
        $this->seedLogin();
        $seen = [];
        HttpTransport::fake(function (string $url, string $method, ?array $payload) use (&$seen): array {
            $seen[] = compact('url', 'method', 'payload');

            return ['status' => 200, 'body' => [], 'raw' => '{"status":"success"}'];
        });

        $tester = new CommandTester(new AuthLogout());
        $exit = $tester->execute([]);

        $this->assertSame(0, $exit, $tester->getDisplay());
        $this->assertSame('POST', $seen[0]['method']);
        $this->assertSame('https://example.invalid/api/cli/logout', $seen[0]['url']);
        $this->assertSame(['token' => 'test-token'], $seen[0]['payload']);
        $this->assertSame(0, $this->tokenCount());
        $this->assertStringContainsString('Successfully logged out user: tester', $tester->getDisplay());
    }

    public function test_logout_on_204_clears_the_local_token_and_succeeds(): void
    {
        $this->seedLogin();
        $calls = 0;
        HttpTransport::fake(function () use (&$calls): array {
            $calls++;

            return ['status' => 204, 'body' => [], 'raw' => ''];
        });

        $tester = new CommandTester(new AuthLogout());
        $exit = $tester->execute([]);

        $this->assertSame(0, $exit, $tester->getDisplay());
        $this->assertSame(1, $calls);
        $this->assertSame(0, $this->tokenCount());
    }

    public function test_logout_on_network_failure_clears_the_local_token_and_returns_non_zero(): void
    {
        $this->seedLogin();
        $calls = 0;
        HttpTransport::fake(function () use (&$calls): ?array {
            $calls++;

            return null;
        });

        $tester = new CommandTester(new AuthLogout());
        $exit = $tester->execute([]);

        $this->assertSame(1, $exit);
        $this->assertSame(3, $calls, 'Transport failures are retried, then reported.');
        $this->assertSame(0, $this->tokenCount(), 'Local cleanup must happen even when the server is unreachable.');
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Local session cleared for tester', $display);
        $this->assertStringContainsString('server revocation failed', $display);
        $this->assertStringContainsString('unknown transport error', $display);
    }

    public function test_logout_on_401_clears_the_local_token_and_returns_non_zero(): void
    {
        $this->seedLogin();
        HttpTransport::fake(fn (): array => [
            'status' => 401,
            'body' => [],
            'raw' => '{"message":"Invalid token"}',
        ]);

        $tester = new CommandTester(new AuthLogout());
        $exit = $tester->execute([]);

        $this->assertSame(1, $exit);
        $this->assertSame(0, $this->tokenCount());
        $display = $tester->getDisplay();
        $this->assertStringContainsString('Local session cleared for tester', $display);
        $this->assertStringContainsString('API returned status 401: Invalid token', $display);
    }

    public function test_logout_without_a_session_is_a_no_op(): void
    {
        HttpTransport::fake(function (): array {
            $this->fail('No request should be made without a local session.');
        });

        $tester = new CommandTester(new AuthLogout());
        $exit = $tester->execute([]);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString('not logged in', $tester->getDisplay());
    }

    public function test_active_user_marker_wins_over_a_later_expiring_cached_user(): void
    {
        $this->seedLogin('alice', 'alice-token', '2030-01-01 00:00:00');
        $this->seedLogin('bob', 'bob-token', '2035-01-01 00:00:00');
        config_set(AuthContext::ACTIVE_USER_KEY, 'alice');

        $context = new AuthContext(new PDO('sqlite:' . config('db_path')));

        $this->assertSame('alice', $context->currentUser());
        $this->assertSame('alice-token', $context->tokenFor('alice'));
    }

    public function test_an_expired_marker_falls_back_to_the_newest_valid_user(): void
    {
        $this->seedLogin('stale', 'stale-token', '2020-01-01 00:00:00');
        $this->seedLogin('fresh', 'fresh-token', '2030-01-01 00:00:00');
        config_set(AuthContext::ACTIVE_USER_KEY, 'stale');

        $context = new AuthContext(new PDO('sqlite:' . config('db_path')));

        $this->assertSame('fresh', $context->currentUser());
    }

    public function test_storing_a_token_marks_that_user_active(): void
    {
        $store = new class {
            use CliStore;

            public function put(string $user, string $token): void
            {
                $this->storeToken($user, $token, '2030-01-01 00:00:00');
            }
        };

        $store->put('carol', 'carol-token');

        $this->assertSame('carol', config_get(AuthContext::ACTIVE_USER_KEY));
    }

    public function test_environment_fallback_is_namespaced_and_warns(): void
    {
        putenv('AUTH_TOKEN=decoy-auth');
        putenv('TURSO_AUTH=decoy-turso');
        putenv('TOKEN=decoy-token');
        putenv(AuthContext::TOKEN_ENV . '=env-token');

        $warnings = [];
        $context = new AuthContext(
            new PDO('sqlite:' . config('db_path')),
            function (string $message) use (&$warnings): void {
                $warnings[] = $message;
            }
        );

        try {
            $this->assertSame('env-token', $context->tokenFor('anyone'));
            $this->assertCount(1, $warnings);
            $this->assertStringContainsString('LIBSQL3_AUTH_TOKEN', $warnings[0]);
        } finally {
            putenv('AUTH_TOKEN');
            putenv('TURSO_AUTH');
            putenv('TOKEN');
            putenv(AuthContext::TOKEN_ENV);
        }
    }

    public function test_a_valid_stored_token_wins_over_the_environment_fallback(): void
    {
        $this->seedLogin('dana', 'dana-token');
        putenv(AuthContext::TOKEN_ENV . '=env-token');

        $warnings = [];
        $context = new AuthContext(
            new PDO('sqlite:' . config('db_path')),
            function (string $message) use (&$warnings): void {
                $warnings[] = $message;
            }
        );

        try {
            $this->assertSame('dana-token', $context->tokenFor('dana'));
            $this->assertSame([], $warnings, 'The fallback must stay silent when a stored token exists.');
        } finally {
            putenv(AuthContext::TOKEN_ENV);
        }
    }

    private function tokenCount(): int
    {
        $pdo = new PDO('sqlite:' . config('db_path'));

        return (int) $pdo->query('SELECT COUNT(*) FROM ' . config('tables.tokens'))->fetchColumn();
    }
}
