<?php

declare(strict_types=1);

namespace Libsql3\Tests;

use Libsql3\Cmd\AuthLogin;
use Libsql3\Cmd\DbList;
use Libsql3\Cmd\DbTokenCreate;
use Libsql3\Cmd\DbShell;
use Libsql3\Cmd\TeamRestore;
use Libsql3\Internal\HttpTransport;
use Psy\Configuration;
use Psy\Shell;
use ReflectionMethod;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * Characterization tests: they pin the HTTP contract the CLI uses TODAY,
 * including known-broken paths. A `@todo` here means "current behavior is
 * wrong and the owning plan will flip this assertion" — never "this test
 * should be deleted".
 */
final class ContractTest extends Libsql3TestCase
{
    public function test_auth_login_posts_credentials_and_consumes_token_fields(): void
    {
        $seen = [];
        HttpTransport::fake(function (string $url, string $method, ?array $payload, array $headers) use (&$seen): array {
            $seen = compact('url', 'method', 'payload', 'headers');

            return [
                'status' => 200,
                'body' => [],
                'raw' => json_encode(['data' => ['token' => 'test-token', 'expires_at' => '2030-01-01 00:00:00']]),
            ];
        });

        $command = new AuthLogin();
        $command->setAppConfig([]);
        $authenticate = new ReflectionMethod($command, 'authenticate');
        $result = $authenticate->invoke($command, 'tester', 'secret');

        $this->assertSame('test-token', $result['token']);
        $this->assertSame('2030-01-01 00:00:00', $result['expires_at']);
        $this->assertSame('https://example.invalid/api/cli/login', $seen['url']);
        $this->assertSame('POST', $seen['method']);
        $this->assertSame(['username' => 'tester', 'password' => 'secret'], $seen['payload']);
        foreach ($seen['headers'] as $header) {
            $this->assertStringNotContainsString('secret', $header);
        }
    }

    public function test_db_list_sends_bearer_token_and_reads_databases(): void
    {
        $this->seedLogin();
        $seen = [];
        HttpTransport::fake(function (string $url, string $method, ?array $payload, array $headers) use (&$seen): array {
            $seen = compact('url', 'method', 'headers');

            return [
                'status' => 200,
                'body' => [],
                'raw' => json_encode(['data' => ['databases' => []]]),
            ];
        });

        $tester = new CommandTester(new DbList());
        $exit = $tester->execute([]);

        $this->assertSame(0, $exit);
        $this->assertSame('https://example.invalid/api/cli/db/lists', $seen['url']);
        $this->assertSame('GET', $seen['method']);
        $this->assertContains('Authorization: Bearer test-token', $seen['headers']);
        $this->assertStringContainsString('No databases found for user: tester', $tester->getDisplay());
    }

    public function test_db_token_create_accepts_200_and_201_and_prints_plaintext_once(): void
    {
        $this->seedLogin();
        $seen = [];
        HttpTransport::fake(function (string $url, string $method, ?array $payload, array $headers) use (&$seen): array {
            $seen = compact('url', 'method', 'headers');

            return [
                'status' => 201,
                'body' => [],
                'raw' => json_encode(['data' => ['token' => 'one-shot-token', 'fingerprint' => 'fp-1']]),
            ];
        });

        $tester = new CommandTester(new DbTokenCreate());
        $exit = $tester->execute(['database' => 'my db']);

        $this->assertSame(0, $exit);
        $this->assertSame('https://example.invalid/api/cli/db/token/mint/my+db', $seen['url']);
        $this->assertSame('POST', $seen['method']);
        $this->assertContains('Authorization: Bearer test-token', $seen['headers']);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('one-shot-token', $display);
        $this->assertStringContainsString('Fingerprint: fp-1', $display);
    }

    /**
     * @todo F-02: the mint endpoint returns 201, but DbShell only accepts 200.
     * This assertion documents the bug; Plan 003 must flip it to a success path.
     */
    public function test_db_shell_currently_rejects_the_201_from_the_token_mint_endpoint(): void
    {
        HttpTransport::fake(fn (): array => [
            'status' => 201,
            'body' => [],
            'raw' => json_encode(['data' => ['token' => 'full-access-token']]),
        ]);

        $command = new DbShell();
        $getDatabaseToken = new ReflectionMethod($command, 'getDatabaseToken');

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('API returned status 201');
        $getDatabaseToken->invoke($command, 'demo', 'tester', 'test-token');
    }

    /**
     * @todo F-05: team:restore calls two routes absent from api.cli.php. This
     * test pins the URLs and the current outcome: the 404 is swallowed by
     * http_request()'s 200-only retry loop, so the user sees a generic retry
     * error rather than a useful API message. Plan 005 must add the routes and
     * flip the expected outcome.
     */
    public function test_team_restore_calls_archives_and_restore_routes(): void
    {
        $this->seedLogin();
        $seen = [];
        HttpTransport::fake(function (string $url, string $method) use (&$seen): array {
            $seen[] = "$method $url";

            if (str_ends_with($url, '/api/cli/team/archives')) {
                return [
                    'status' => 200,
                    'body' => [],
                    'raw' => json_encode(['data' => ['teams' => [[
                        'id' => 7,
                        'name' => 'Archived Team',
                        'deleted_at' => '2026-01-01 00:00:00',
                    ]]]]),
                ];
            }

            return ['status' => 404, 'body' => [], 'raw' => json_encode([
                'status' => false,
                'data' => ['message' => 'route missing'],
            ])];
        });

        $command = new TeamRestore();
        $command->setAppConfig([]);
        // TeamRestore asks for the "question" helper even when a team_id is
        // supplied. PsySH commands require a Psy\Shell as their application.
        $command->setApplication(new Shell(new Configuration()));
        $tester = new CommandTester($command);
        $tester->setInputs(['y']);
        $exit = $tester->execute(['team_id' => '7']);

        $this->assertSame(3, $exit);
        $this->assertContains('GET https://example.invalid/api/cli/team/archives', $seen);
        $this->assertContains('POST https://example.invalid/api/cli/team/restore/7', $seen);
        $this->assertStringContainsString('Could not get a valid response after 2 retries', $tester->getDisplay());
    }
}
