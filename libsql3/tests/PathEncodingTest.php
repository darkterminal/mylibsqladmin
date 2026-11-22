<?php

declare(strict_types=1);

namespace Libsql3\Tests;

use Libsql3\Cmd\DbTokenStatus;
use Libsql3\Cmd\TeamFind;
use Libsql3\Internal\HttpTransport;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * F-49: urlencode() is form encoding — it turns a space into '+', which Laravel
 * never decodes in a URL path. Path segments must use rawurlencode() ('%20').
 */
final class PathEncodingTest extends Libsql3TestCase
{
    public function test_team_find_encodes_spaces_as_percent_twenty_in_the_path(): void
    {
        $this->seedLogin();
        $seen = [];
        HttpTransport::fake(function (string $url) use (&$seen): array {
            $seen[] = $url;

            return [
                'status' => 200,
                'body' => [],
                'raw' => json_encode([
                    'status' => 'success',
                    'data' => ['team' => ['id' => 21, 'name' => 'CLI Smoke Team', 'description' => '']],
                ]),
            ];
        });

        $tester = new CommandTester(new TeamFind());
        $this->assertSame(0, $tester->execute(['team_name' => 'CLI Smoke Team']), $tester->getDisplay());
        $this->assertSame(
            ['https://example.invalid/api/cli/team/find/CLI%20Smoke%20Team'],
            $seen
        );
    }

    public function test_database_path_segment_also_uses_percent_encoding(): void
    {
        $this->seedLogin();
        $seen = [];
        HttpTransport::fake(function (string $url) use (&$seen): array {
            $seen[] = $url;

            return [
                'status' => 200,
                'body' => [],
                'raw' => json_encode(['status' => 'success', 'data' => []]),
            ];
        });

        $tester = new CommandTester(new DbTokenStatus());
        $this->assertSame(0, $tester->execute(['database_name' => 'cli smoke db']), $tester->getDisplay());
        $this->assertSame(
            ['https://example.invalid/api/cli/db/token/cli%20smoke%20db'],
            $seen
        );
    }

    public function test_no_command_uses_form_encoding_for_path_segments(): void
    {
        $offenders = [];
        foreach (glob(__DIR__ . '/../src/Cmd/*.php') as $file) {
            if (preg_match('/(?<!raw)urlencode\s*\(/', (string) file_get_contents($file))) {
                $offenders[] = basename($file);
            }
        }

        $this->assertSame([], $offenders, 'Path segments must use rawurlencode(), which encodes spaces as %20');
    }
}
