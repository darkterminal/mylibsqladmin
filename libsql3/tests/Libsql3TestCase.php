<?php

declare(strict_types=1);

namespace Libsql3\Tests;

use Libsql3\Internal\HttpTransport;
use PDO;
use PHPUnit\Framework\TestCase;

/**
 * Isolates every test in its own HOME so the local SQLite store and config
 * never leak between cases. No real network request can escape: the transport
 * is reset before and after each test, and every HTTP-using test installs a
 * fake.
 */
abstract class Libsql3TestCase extends TestCase
{
    protected string $home;

    protected function setUp(): void
    {
        $this->home = sys_get_temp_dir() . '/libsql3-test-' . bin2hex(random_bytes(8));
        mkdir($this->home, 0700, true);

        putenv('HOME=' . $this->home);
        putenv('LIBSQL_API_ENDPOINT=https://example.invalid');

        initialize_local_store();
        // Mirror a logged-in CLI: http_request() consults this config row first.
        config_set('LIBSQL_API_ENDPOINT', 'https://example.invalid');

        HttpTransport::reset();
    }

    protected function tearDown(): void
    {
        HttpTransport::reset();
        putenv('HOME');
        putenv('LIBSQL_API_ENDPOINT');

        $this->removeTree($this->home);
    }

    protected function seedLogin(string $username = 'tester', string $token = 'test-token'): void
    {
        $pdo = new PDO('sqlite:' . config('db_path'));
        $pdo->exec(sprintf(
            'INSERT INTO %s (username, token, expires_at) VALUES (%s, %s, %s)',
            config('tables.tokens'),
            $pdo->quote($username),
            $pdo->quote($token),
            $pdo->quote('2030-01-01 00:00:00')
        ));
    }

    private function removeTree(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );
        foreach ($items as $item) {
            $item->isDir() ? rmdir($item->getPathname()) : unlink($item->getPathname());
        }
        rmdir($dir);
    }
}
