<?php

declare(strict_types=1);

namespace Libsql3\Tests;

use Libsql3\Cmd\AuthLogin;
use Libsql3\Cmd\DbCreate;
use Libsql3\Cmd\DbDelete;
use Libsql3\Cmd\DbList;
use Libsql3\Cmd\DbTokenCreate;
use Libsql3\Cmd\DbTokenStatus;
use Libsql3\Cmd\DbShell;
use Libsql3\Cmd\GroupCreate;
use Libsql3\Cmd\TeamFind;
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
                'raw' => json_encode(['data' => [
                    'token' => 'test-token',
                    'expires_at' => '2030-01-01 00:00:00',
                    'app_url' => 'http://localhost:8000',
                ]]),
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

        // F-03: app_url is operator-owned data, never an endpoint source.
        $this->assertSame('https://example.invalid', config_get('LIBSQL_API_ENDPOINT'));
        $this->assertEmpty(config_get('LIBSQL_DATABASE_ENDPOINT'));
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
     * F-02 fixed: the mint endpoint's 201 is accepted and its plaintext token
     * is consumed by db:shell. The shared parser is the single place that
     * decides which statuses and payload shapes are valid.
     */
    public function test_db_shell_accepts_201_from_the_token_mint_endpoint(): void
    {
        HttpTransport::fake(fn (): array => [
            'status' => 201,
            'body' => [],
            'raw' => json_encode(['data' => ['token' => 'full-access-token']]),
        ]);

        $command = new DbShell();
        $getDatabaseToken = new ReflectionMethod($command, 'getDatabaseToken');

        $this->assertSame(
            'full-access-token',
            $getDatabaseToken->invoke($command, 'demo', 'tester', 'test-token')
        );
    }

    public function test_db_shell_accepts_200_from_the_token_mint_endpoint(): void
    {
        HttpTransport::fake(fn (): array => [
            'status' => 200,
            'body' => [],
            'raw' => json_encode(['data' => ['token' => 'legacy-token']]),
        ]);

        $command = new DbShell();
        $getDatabaseToken = new ReflectionMethod($command, 'getDatabaseToken');

        $this->assertSame(
            'legacy-token',
            $getDatabaseToken->invoke($command, 'demo', 'tester', 'test-token')
        );
    }

    public function test_mint_parser_surfaces_server_message_and_rejects_missing_token(): void
    {
        $serverError = parse_database_token_mint_response([
            'status' => 422,
            'raw' => json_encode(['message' => 'database is archived']),
        ]);
        $this->assertFalse($serverError['ok']);
        $this->assertSame('status', $serverError['kind']);
        $this->assertSame('database is archived', $serverError['error']);

        $missing = parse_database_token_mint_response([
            'status' => 201,
            'raw' => json_encode(['data' => []]),
        ]);
        $this->assertFalse($missing['ok']);
        $this->assertSame('malformed', $missing['kind']);
        $this->assertSame('Malformed response from server', $missing['error']);
    }

    /**
     * F-05 fixed: both routes now exist on the platform and the CLI consumes
     * the boolean status envelope from CliTeamRestoreController.
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

            return [
                'status' => 200,
                'body' => [],
                'raw' => json_encode([
                    'status' => true,
                    'data' => ['team' => ['id' => 7, 'name' => 'Archived Team', 'description' => 'restored']],
                ]),
            ];
        });

        $command = new TeamRestore();
        $command->setAppConfig([]);
        // TeamRestore asks for the "question" helper even when a team_id is
        // supplied. PsySH commands require a Psy\Shell as their application.
        $command->setApplication(new Shell(new Configuration()));
        $tester = new CommandTester($command);
        $tester->setInputs(['y']);
        $exit = $tester->execute(['team_id' => '7']);

        $this->assertSame(0, $exit, $tester->getDisplay());
        $this->assertContains('GET https://example.invalid/api/cli/team/archives', $seen);
        $this->assertContains('POST https://example.invalid/api/cli/team/restore/7', $seen);
        $this->assertStringContainsString('Team restored successfully!', $tester->getDisplay());
    }

    public function test_team_restore_surfaces_a_false_status_envelope(): void
    {
        $this->seedLogin();
        HttpTransport::fake(function (string $url): array {
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

            return [
                'status' => 200,
                'body' => [],
                'raw' => json_encode(['status' => false, 'data' => ['message' => 'restore denied']]),
            ];
        });

        $command = new TeamRestore();
        $command->setAppConfig([]);
        $command->setApplication(new Shell(new Configuration()));
        $tester = new CommandTester($command);
        $tester->setInputs(['y']);
        $exit = $tester->execute(['team_id' => '7']);

        $this->assertSame(3, $exit);
        $this->assertStringContainsString('API error: restore denied', $tester->getDisplay());
        $this->assertStringNotContainsString('Undefined array key', $tester->getDisplay());
    }

    public function test_db_create_accepts_numeric_team_and_group_ids(): void
    {
        $this->seedLogin();
        $seen = $this->fakeCliApi(
            [
                ['id' => 3, 'name' => 'Team A', 'created_at' => '2026-01-01 00:00:00'],
                ['id' => 9, 'name' => 'Team B', 'created_at' => '2026-01-02 00:00:00'],
            ],
            [
                ['id' => 2, 'name' => 'Group A'],
                ['id' => 8, 'name' => 'Group B'],
            ]
        );

        $command = new DbCreate();
        $command->setAppConfig([]);
        $command->setApplication(new Shell(new Configuration()));
        $tester = new CommandTester($command);
        $exit = $tester->execute(['name' => 'demo', '--team' => '3', '--group' => '2']);

        $this->assertSame(0, $exit, $tester->getDisplay());
        $create = $this->requestTo($seen, '/api/cli/db/create');
        $this->assertSame('3', (string) $create['payload']['team_id']);
        $this->assertSame('2', (string) $create['payload']['group_id']);
        $this->assertStringContainsString('Using team: Team A (ID: 3)', $tester->getDisplay());
        $this->assertStringContainsString('Using group: Group A (ID: 2)', $tester->getDisplay());
    }

    public function test_db_create_surfaces_a_422_message_without_replaying_the_post(): void
    {
        $this->seedLogin();
        $seen = [];
        HttpTransport::fake(function (string $url, string $method) use (&$seen): array {
            $seen[] = "$method $url";

            if (str_ends_with($url, '/api/cli/team/lists')) {
                return [
                    'status' => 200,
                    'body' => [],
                    'raw' => json_encode(['data' => ['teams' => [
                        ['id' => 3, 'name' => 'Team A', 'created_at' => '2026-01-01 00:00:00'],
                    ]]]),
                ];
            }

            if (str_ends_with($url, '/api/cli/group/lists')) {
                return [
                    'status' => 200,
                    'body' => [],
                    'raw' => json_encode(['data' => ['groups' => [
                        ['id' => 2, 'name' => 'Group A'],
                    ]]]),
                ];
            }

            return [
                'status' => 422,
                'body' => [],
                'raw' => json_encode([
                    'message' => 'The group_id field is invalid.',
                    'errors' => ['group_id' => ['The selected group id is invalid.']],
                ]),
            ];
        });

        $command = new DbCreate();
        $command->setAppConfig([]);
        $command->setApplication(new Shell(new Configuration()));
        $tester = new CommandTester($command);
        $exit = $tester->execute(['name' => 'demo', '--team' => '3', '--group' => '2']);

        $this->assertSame(3, $exit);
        $createCalls = array_values(array_filter(
            $seen,
            fn (string $request) => $request === 'POST https://example.invalid/api/cli/db/create'
        ));
        $this->assertCount(1, $createCalls, 'A 422 must not be retried as a mutation.');
        $display = preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertStringContainsString('API returned status 422: The group_id field is invalid.', $display);
        $this->assertStringNotContainsString('Could not get a valid response after 2 retries', $display);
    }

    public function test_db_create_omits_the_id_column_when_the_platform_returns_no_id(): void
    {
        $this->seedLogin();
        HttpTransport::fake(function (string $url): array {
            if (str_ends_with($url, '/api/cli/team/lists')) {
                return [
                    'status' => 200,
                    'body' => [],
                    'raw' => json_encode(['data' => ['teams' => [
                        ['id' => 3, 'name' => 'Team A', 'created_at' => '2026-01-01 00:00:00'],
                    ]]]),
                ];
            }

            if (str_ends_with($url, '/api/cli/group/lists')) {
                return [
                    'status' => 200,
                    'body' => [],
                    'raw' => json_encode(['data' => ['groups' => [
                        ['id' => 2, 'name' => 'Group A'],
                    ]]]),
                ];
            }

            return [
                'status' => 201,
                'body' => [],
                'raw' => json_encode(['data' => ['database' => [
                    'name' => 'demo',
                    'team_name' => 'Team A',
                    'group_name' => 'Group A',
                    'is_schema' => false,
                    'created_at' => '2026-01-01 00:00:00',
                ]]]),
            ];
        });

        $command = new DbCreate();
        $command->setAppConfig([]);
        $command->setApplication(new Shell(new Configuration()));
        $tester = new CommandTester($command);
        $exit = $tester->execute(['name' => 'demo', '--team' => '3', '--group' => '2']);

        $this->assertSame(0, $exit, $tester->getDisplay());
        $display = preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertDoesNotMatchRegularExpression('/ID\s*[│|]\s*Name/', $display);
        $this->assertStringContainsString('Name', $display);
        $this->assertStringContainsString("Database 'demo' created successfully!", $display);
    }

    public function test_db_token_status_prints_only_the_masked_fingerprint(): void
    {
        $this->seedLogin();
        $seen = [];
        HttpTransport::fake(function (string $url, string $method, ?array $payload, array $headers) use (&$seen): array {
            $seen[] = compact('url', 'method', 'headers');

            return [
                'status' => 200,
                'body' => [],
                'raw' => json_encode([
                    'status' => 'success',
                    'data' => ['token' => '••••••••abcd1234'],
                ]),
            ];
        });

        $tester = new CommandTester(new DbTokenStatus());
        $exit = $tester->execute(['database_name' => 'my db']);

        $this->assertSame(0, $exit);
        $this->assertSame('GET', $seen[0]['method']);
        $this->assertSame('https://example.invalid/api/cli/db/token/my+db', $seen[0]['url']);
        $this->assertContains('Authorization: Bearer test-token', $seen[0]['headers']);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('••••••••abcd1234', $display);
        $this->assertStringNotContainsString('raw-token', $display);
    }

    public function test_db_token_status_does_not_fabricate_a_missing_token(): void
    {
        $this->seedLogin();
        HttpTransport::fake(fn (): array => [
            'status' => 200,
            'body' => [],
            'raw' => json_encode(['status' => 'success', 'data' => ['token' => null]]),
        ]);

        $tester = new CommandTester(new DbTokenStatus());
        $exit = $tester->execute(['database_name' => 'demo']);

        $this->assertSame(0, $exit);
        $this->assertStringContainsString("No token exists for database 'demo'.", $tester->getDisplay());
    }

    public function test_team_find_reaches_the_find_endpoint_and_returns_team_fields(): void
    {
        $this->seedLogin();
        $seen = [];
        HttpTransport::fake(function (string $url, string $method) use (&$seen): array {
            $seen[] = "$method $url";

            return [
                'status' => 200,
                'body' => [],
                'raw' => json_encode([
                    'status' => 'success',
                    'data' => ['team' => ['id' => 7, 'name' => 'Team A', 'description' => 'first team']],
                ]),
            ];
        });

        $tester = new CommandTester(new TeamFind());
        $exit = $tester->execute(['team_name' => 'Team A']);

        $this->assertSame(0, $exit);
        $this->assertSame(['GET https://example.invalid/api/cli/team/find/Team+A'], $seen);
        $display = preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertStringContainsString('Team found successfully!', $display);
        $this->assertStringContainsString('Team A', $display);
        $this->assertStringContainsString('first team', $display);
    }

    public function test_team_find_surfaces_the_platform_message_when_no_team_exists(): void
    {
        $this->seedLogin();
        HttpTransport::fake(fn (): array => [
            'status' => 404,
            'body' => [],
            'raw' => json_encode(['status' => 'error', 'message' => 'No team found', 'data' => []]),
        ]);

        $tester = new CommandTester(new TeamFind());
        $exit = $tester->execute(['team_name' => 'missing']);

        $this->assertSame(3, $exit);
        $display = preg_replace('/\s+/', ' ', $tester->getDisplay());
        $this->assertStringContainsString('API returned status 404: No team found', $display);
    }

    public function test_group_create_accepts_numeric_team_id(): void
    {
        $this->seedLogin();
        $seen = $this->fakeCliApi(
            [
                ['id' => 3, 'name' => 'Team A', 'created_at' => '2026-01-01 00:00:00'],
                ['id' => 9, 'name' => 'Team B', 'created_at' => '2026-01-02 00:00:00'],
            ],
            []
        );

        $command = new GroupCreate();
        $command->setAppConfig([]);
        $command->setApplication(new Shell(new Configuration()));
        $tester = new CommandTester($command);
        $exit = $tester->execute(['name' => 'demo', '--team' => '3']);

        $this->assertSame(0, $exit, $tester->getDisplay());
        $create = $this->requestTo($seen, '/api/cli/group/create');
        $this->assertSame('3', (string) $create['payload']['team_id']);
    }

    public function test_db_create_uses_defaults_from_the_local_store(): void
    {
        $this->seedLogin();
        config_set('DEFAULT_TEAM', '3');
        config_set('DEFAULT_GROUP', '2');

        $seen = $this->fakeCliApi(
            [
                ['id' => 3, 'name' => 'Team A', 'created_at' => '2026-01-01 00:00:00'],
                ['id' => 9, 'name' => 'Team B', 'created_at' => '2026-01-02 00:00:00'],
            ],
            [
                ['id' => 2, 'name' => 'Group A'],
                ['id' => 8, 'name' => 'Group B'],
            ]
        );

        $command = new DbCreate();
        $command->setAppConfig([]);
        $command->setApplication(new Shell(new Configuration()));
        $tester = new CommandTester($command);
        $exit = $tester->execute(['name' => 'demo']);

        $this->assertSame(0, $exit, $tester->getDisplay());
        $create = $this->requestTo($seen, '/api/cli/db/create');
        $this->assertSame('3', (string) $create['payload']['team_id']);
        $this->assertSame('2', (string) $create['payload']['group_id']);
        $this->assertStringContainsString('Using configured default team: Team A (ID: 3)', $tester->getDisplay());
        $this->assertStringContainsString('Using configured default group: Group A (ID: 2)', $tester->getDisplay());
    }

    public function test_db_delete_force_skips_confirmation_and_calls_force_endpoint(): void
    {
        $this->seedLogin();
        $seen = [];
        HttpTransport::fake(function (string $url) use (&$seen): array {
            $seen[] = $url;

            return ['status' => 204, 'body' => [], 'raw' => ''];
        });

        $tester = new CommandTester(new DbDelete());
        $exit = $tester->execute(['database_name' => 'demo', '--force' => true]);

        $this->assertSame(0, $exit, $tester->getDisplay());
        $this->assertSame(['https://example.invalid/api/cli/db/force-delete/demo'], $seen);
        $this->assertStringNotContainsString('Are you sure', $tester->getDisplay());
        $this->assertStringContainsString("Database 'demo' deleted successfully!", $tester->getDisplay());
    }

    public function test_db_delete_declining_confirmation_returns_exit_code_4(): void
    {
        $this->seedLogin();
        $seen = [];
        HttpTransport::fake(function (string $url) use (&$seen): array {
            $seen[] = $url;

            return ['status' => 204, 'body' => [], 'raw' => ''];
        });

        $tester = new CommandTester(new DbDelete());
        $exit = $tester->execute(['database_name' => 'demo']);

        $this->assertSame(4, $exit, $tester->getDisplay());
        $this->assertSame([], $seen);
        $this->assertStringContainsString('Database deletion cancelled', $tester->getDisplay());
    }

    public function test_db_delete_accepting_confirmation_calls_delete_endpoint(): void
    {
        $this->seedLogin();
        $seen = [];
        HttpTransport::fake(function (string $url) use (&$seen): array {
            $seen[] = $url;

            return ['status' => 204, 'body' => [], 'raw' => ''];
        });

        $tester = new CommandTester(new DbDelete());
        $tester->setInputs(['y']);
        $exit = $tester->execute(['database_name' => 'demo']);

        $this->assertSame(0, $exit, $tester->getDisplay());
        $this->assertSame(['https://example.invalid/api/cli/db/delete/demo'], $seen);
    }

    /**
     * Installs a fake API with multiple teams/groups so "only one available"
     * cannot accidentally satisfy a resolver test.
     */
    private function fakeCliApi(array $teams, array $groups): \ArrayObject
    {
        $seen = new \ArrayObject();

        HttpTransport::fake(function (string $url, string $method, ?array $payload) use ($seen, $teams, $groups): array {
            $seen[] = ['url' => $url, 'method' => $method, 'payload' => $payload];

            if (str_ends_with($url, '/api/cli/team/lists')) {
                return [
                    'status' => 200,
                    'body' => [],
                    'raw' => json_encode(['data' => ['teams' => $teams]]),
                ];
            }

            if (str_ends_with($url, '/api/cli/group/lists')) {
                return [
                    'status' => 200,
                    'body' => [],
                    'raw' => json_encode(['data' => ['groups' => $groups]]),
                ];
            }

            if (str_ends_with($url, '/api/cli/db/create')) {
                return [
                    'status' => 201,
                    'body' => [],
                    'raw' => json_encode(['data' => ['database' => [
                        'id' => 11,
                        'name' => 'demo',
                        'team_name' => 'Team A',
                        'group_name' => 'Group A',
                        'is_schema' => false,
                        'created_at' => '2026-01-01 00:00:00',
                    ]]]),
                ];
            }

            if (str_ends_with($url, '/api/cli/group/create')) {
                return [
                    'status' => 200,
                    'body' => [],
                    'raw' => json_encode([
                        'status' => true,
                        'data' => ['group' => [
                            'name' => 'demo',
                            'team_name' => 'Team A',
                            'created_at' => '2026-01-01 00:00:00',
                        ]],
                    ]),
                ];
            }

            throw new \RuntimeException("Unexpected request: $method $url");
        });

        return $seen;
    }

    private function requestTo(\ArrayObject $seen, string $path): array
    {
        foreach ($seen as $request) {
            if (str_ends_with($request['url'], $path)) {
                return $request;
            }
        }

        $this->fail("No request recorded for $path");
    }
}
