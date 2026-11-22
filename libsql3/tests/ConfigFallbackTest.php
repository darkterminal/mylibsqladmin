<?php

declare(strict_types=1);

namespace Libsql3\Tests;

use Libsql3\Cmd\ConfigGet;
use Libsql3\Cmd\DbList;
use Libsql3\Internal\HttpTransport;
use PDO;
use Symfony\Component\Console\Tester\CommandTester;

final class ConfigFallbackTest extends Libsql3TestCase
{
    public function test_missing_config_key_returns_null(): void
    {
        $this->assertNull(config_get('DEFINITELY_MISSING'));
    }

    public function test_config_get_command_warns_for_a_missing_key(): void
    {
        $tester = new CommandTester(new ConfigGet());
        $exit = $tester->execute(['key' => 'DEFINITELY_MISSING']);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString(
            "Configuration key 'DEFINITELY_MISSING' not found",
            $tester->getDisplay()
        );
    }

    public function test_http_request_uses_env_endpoint_when_stored_row_is_absent(): void
    {
        putenv('LIBSQL_API_ENDPOINT=https://env-only.invalid');

        $pdo = new PDO('sqlite:' . config('db_path'));
        $pdo->exec('DELETE FROM ' . config('tables.config') . " WHERE key = 'LIBSQL_API_ENDPOINT'");
        $this->assertNull(config_get('LIBSQL_API_ENDPOINT'));

        $this->seedLogin();

        $seen = [];
        HttpTransport::fake(function (string $url) use (&$seen): array {
            $seen[] = $url;

            return [
                'status' => 200,
                'body' => [],
                'raw' => json_encode(['data' => ['databases' => []]]),
            ];
        });

        $tester = new CommandTester(new DbList());
        $this->assertSame(0, $tester->execute([]));
        $this->assertSame(['https://env-only.invalid/api/cli/db/lists'], $seen);
    }
}
