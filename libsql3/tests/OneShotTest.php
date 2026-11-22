<?php

declare(strict_types=1);

namespace Libsql3\Tests;

use Libsql3\Internal\OneShotRunner;
use Libsql3\Version;

final class OneShotTest extends Libsql3TestCase
{
    public function test_adapter_only_claims_explicit_non_repl_arguments(): void
    {
        $this->assertFalse(OneShotRunner::isOneShot([]));
        $this->assertFalse(OneShotRunner::isOneShot(['libsql3']));
        $this->assertFalse(OneShotRunner::isOneShot(['libsql3', 'repl']));
        $this->assertTrue(OneShotRunner::isOneShot(['libsql3', 'config:list']));
    }

    public function test_version_is_one_line_without_needing_an_endpoint(): void
    {
        [$status, $stdout, $stderr] = $this->runEntrypoint(['--version'], [
            'LIBSQL_API_ENDPOINT' => '',
        ]);

        $this->assertSame(0, $status, $stderr);
        $this->assertSame('libsql3 ' . Version::CURRENT, trim($stdout));
        $this->assertSame(1, substr_count(rtrim($stdout, "\r\n"), "\n") + 1);
    }

    public function test_command_runs_without_a_prompt(): void
    {
        [$status, $stdout, $stderr] = $this->runEntrypoint(['config:list']);

        $this->assertSame(0, $status, $stderr);
        $this->assertStringContainsString('LIBSQL_API_ENDPOINT', $stdout);
        $this->assertStringNotContainsString('mylibsqladmin@libsql3>', $stdout);
    }

    public function test_unknown_command_fails_with_a_useful_message(): void
    {
        [$status, $stdout, $stderr] = $this->runEntrypoint(['definitely:missing']);

        $this->assertNotSame(0, $status);
        $this->assertStringContainsString('definitely', $stdout . $stderr);
    }

    /**
     * @param list<string>            $args
     * @param array<string, ?string>  $overrides
     *
     * @return array{int, string, string}
     */
    private function runEntrypoint(array $args, array $overrides = []): array
    {
        $command = implode(' ', array_map('escapeshellarg', array_merge(
            [PHP_BINARY, __DIR__ . '/../libsql3'],
            $args
        )));

        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w'],
        ];

        $env = getenv();
        $env['HOME'] = $this->home;
        $env['LIBSQL_API_ENDPOINT'] = 'https://example.invalid';
        foreach ($overrides as $key => $value) {
            if ($value === null) {
                unset($env[$key]);
            } else {
                $env[$key] = $value;
            }
        }

        $process = proc_open($command, $descriptors, $pipes, null, $env);
        $this->assertIsResource($process);

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $status = proc_close($process);

        return [$status, (string) $stdout, (string) $stderr];
    }
}
