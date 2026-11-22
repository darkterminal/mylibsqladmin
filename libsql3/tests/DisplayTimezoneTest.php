<?php

declare(strict_types=1);

namespace Libsql3\Tests;

use Libsql3\Cmd\AuthLogin;
use Libsql3\Cmd\TeamList;
use Libsql3\Internal\HttpTransport;
use ReflectionMethod;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * F-51: the CLI used to render API timestamps with PHP's default timezone
 * (UTC here), while the web UI renders them in SystemSettings::timezone().
 */
final class DisplayTimezoneTest extends Libsql3TestCase
{
    protected function tearDown(): void
    {
        putenv('LIBSQL3_TIMEZONE');
        parent::tearDown();
    }

    public function test_iso_utc_timestamp_is_converted_to_the_display_zone(): void
    {
        putenv('LIBSQL3_TIMEZONE=Asia/Jakarta');

        $this->assertSame('2026-11-22 18:33:26', format_api_date('2026-11-22T11:33:26.000000Z'));
    }

    public function test_naive_server_timestamp_is_the_utc_instant(): void
    {
        putenv('LIBSQL3_TIMEZONE=Asia/Jakarta');

        $this->assertSame('2026-01-02 10:04:05', format_api_date('2026-01-02 03:04:05'));
        $this->assertSame('2026-01-02', format_api_date('2026-01-02 03:04:05', 'Y-m-d'));
    }

    public function test_explicit_invalid_timezone_fails_closed_to_utc(): void
    {
        putenv('LIBSQL3_TIMEZONE=Not/AZone');

        $this->assertSame('UTC', display_timezone());
        $this->assertSame('2026-11-22 11:33:26', format_api_date('2026-11-22T11:33:26.000000Z'));
    }

    public function test_stored_timezone_is_used_and_an_invalid_stored_value_is_ignored(): void
    {
        config_set('LIBSQL3_DISPLAY_TIMEZONE', 'Asia/Singapore');
        $this->assertSame('Asia/Singapore', display_timezone());

        config_set('LIBSQL3_DISPLAY_TIMEZONE', 'invalid');
        $this->assertSame('UTC', display_timezone());
    }

    public function test_login_caches_the_server_timezone_for_later_commands(): void
    {
        HttpTransport::fake(fn (): array => [
            'status' => 200,
            'body' => [],
            'raw' => json_encode(['data' => [
                'token' => 'test-token',
                'expires_at' => '2030-01-01 00:00:00',
                'timezone' => 'Asia/Jakarta',
            ]]),
        ]);

        $command = new AuthLogin();
        $authenticate = new ReflectionMethod($command, 'authenticate');
        $credentials = $authenticate->invoke($command, 'tester', 'secret');

        $this->assertSame('Asia/Jakarta', $credentials['timezone']);

        $storeToken = new ReflectionMethod($command, 'storeToken');
        $storeToken->invoke($command, 'tester', 'test-token', '2030-01-01 00:00:00', $credentials['timezone']);

        $this->assertSame('Asia/Jakarta', config_get('LIBSQL3_DISPLAY_TIMEZONE'));
        $this->assertSame('Asia/Jakarta', display_timezone());
        $this->assertSame('2026-11-22 18:33:26', format_api_date('2026-11-22T11:33:26.000000Z'));
    }

    public function test_team_list_renders_api_timestamps_in_the_display_zone(): void
    {
        putenv('LIBSQL3_TIMEZONE=Asia/Jakarta');
        $this->seedLogin();

        HttpTransport::fake(fn (): array => [
            'status' => 200,
            'body' => [],
            'raw' => json_encode(['data' => ['teams' => [[
                'id' => 21,
                'name' => 'CLI Smoke Team',
                'description' => 'dari uji coba CLI',
                'created_at' => '2026-11-22T11:33:26.000000Z',
                'updated_at' => '2026-11-22T11:52:49.000000Z',
            ]]]]),
        ]);

        $tester = new CommandTester(new TeamList());
        $this->assertSame(0, $tester->execute([]), $tester->getDisplay());
        $this->assertStringContainsString('2026-11-22 18:33:26', $tester->getDisplay());
        $this->assertStringContainsString('2026-11-22 18:52:49', $tester->getDisplay());
        $this->assertStringNotContainsString('11:33:26', $tester->getDisplay());
    }
}
