<?php

declare(strict_types=1);

namespace Libsql3\Tests;

use Libsql3\Internal\CliStore;
use PDO;

/**
 * The local credential store is a plaintext cache; its filesystem permissions
 * are part of the security boundary, not a cosmetic detail.
 */
final class CliStoreTest extends Libsql3TestCase
{
    public function test_initialization_creates_schema_and_a_0700_directory(): void
    {
        $pdo = initialize_local_store();

        $this->assertInstanceOf(PDO::class, $pdo);
        $this->assertDirectoryExists($this->home . '/.mylibsqladmin');
        $this->assertSame('700', substr(sprintf('%o', fileperms($this->home . '/.mylibsqladmin')), -3));

        foreach ([config('tables.tokens'), config('tables.config')] as $table) {
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type = 'table' AND name = '$table'");
            $this->assertSame($table, $stmt->fetchColumn());
        }
    }

    public function test_initialization_is_idempotent(): void
    {
        initialize_local_store();
        $second = initialize_local_store();

        $this->assertInstanceOf(PDO::class, $second);
        $this->assertSame('1', (string) $second->query('SELECT 1')->fetchColumn());
    }

    public function test_existing_loose_directory_is_tightened(): void
    {
        $dir = $this->home . '/.mylibsqladmin';
        chmod($dir, 0755);

        initialize_local_store();

        clearstatcache(true, $dir);
        $this->assertSame('700', substr(sprintf('%o', fileperms($dir)), -3));
    }

    public function test_token_round_trip_through_the_store(): void
    {
        $store = new class {
            use CliStore;

            public function put(string $user, string $token, string $expiresAt): void
            {
                $this->storeToken($user, $token, $expiresAt);
            }

            public function get(string $user): ?string
            {
                return $this->getToken($user);
            }

            public function currentUser(): ?string
            {
                return $this->getCurrentLoggedInUser();
            }
        };

        $store->put('tester', 'test-token', '2030-01-01 00:00:00');

        $this->assertSame('test-token', $store->get('tester'));
        $this->assertSame('tester', $store->currentUser());
    }

    public function test_store_directory_and_database_file_are_private(): void
    {
        $dir = dirname(config('db_path'));
        $db = config('db_path');

        $this->assertDirectoryExists($dir);
        $this->assertFileExists($db);
        $this->assertSame('700', substr(sprintf('%o', fileperms($dir)), -3));
        $this->assertSame('600', substr(sprintf('%o', fileperms($db)), -3));
    }

    public function test_loose_file_permissions_are_tightened_on_the_next_open(): void
    {
        $dir = dirname(config('db_path'));
        $db = config('db_path');
        chmod($dir, 0755);
        chmod($db, 0644);

        // Any config read opens the store through use_database().
        \config_set('permission-probe', 'value');
        \config_get('permission-probe');

        $this->assertSame('700', substr(sprintf('%o', fileperms($dir)), -3));
        $this->assertSame('600', substr(sprintf('%o', fileperms($db)), -3));
    }
}
