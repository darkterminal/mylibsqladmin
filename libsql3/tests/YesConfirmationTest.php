<?php

declare(strict_types=1);

namespace Libsql3\Tests;

use Libsql3\Cmd\DbDelete;
use Libsql3\Cmd\TeamRestore;
use Libsql3\Internal\HttpTransport;
use Symfony\Component\Console\Tester\CommandTester;

/**
 * F-52: one-shot input is non-interactive, so confirm() silently takes its
 * default (false). --yes is the explicit acknowledgement that lets a script
 * confirm without changing the operation's semantics.
 */
final class YesConfirmationTest extends Libsql3TestCase
{
    public function test_db_delete_yes_confirms_without_a_prompt_and_keeps_the_archive_path(): void
    {
        $this->seedLogin();
        $seen = [];
        HttpTransport::fake(function (string $url) use (&$seen): array {
            $seen[] = $url;

            return ['status' => 204, 'body' => [], 'raw' => ''];
        });

        $tester = new CommandTester(new DbDelete());
        $exit = $tester->execute(
            ['database_name' => 'demo', '--yes' => true],
            ['interactive' => false]
        );

        $this->assertSame(0, $exit, $tester->getDisplay());
        $this->assertSame(['https://example.invalid/api/cli/db/delete/demo'], $seen);
        $this->assertStringNotContainsString('Are you sure', $tester->getDisplay());
        $this->assertStringContainsString('archived (restorable with db:restore)', $tester->getDisplay());
    }

    public function test_team_restore_non_interactive_accepts_yes_instead_of_force(): void
    {
        $this->seedLogin();
        $seen = [];
        HttpTransport::fake(function (string $url, string $method) use (&$seen): array {
            $seen[] = [$method, $url];

            if (str_ends_with($url, '/api/cli/team/archives')) {
                return ['status' => 200, 'body' => [], 'raw' => json_encode(['data' => ['teams' => [
                    ['id' => 7, 'name' => 'Archived Team', 'deleted_at' => '2026-01-01 00:00:00'],
                ]]])];
            }

            return ['status' => 200, 'body' => [], 'raw' => json_encode([
                'status' => true,
                'data' => ['team' => ['id' => 7, 'name' => 'Archived Team']],
            ])];
        });

        $tester = new CommandTester(new TeamRestore());
        $exit = $tester->execute(['team_id' => 7, '--yes' => true], ['interactive' => false]);

        $this->assertSame(0, $exit, $tester->getDisplay());
        $this->assertSame([
            ['GET', 'https://example.invalid/api/cli/team/archives'],
            ['POST', 'https://example.invalid/api/cli/team/restore/7'],
        ], $seen);
    }

    public function test_every_command_with_a_confirmation_prompt_exposes_yes(): void
    {
        $offenders = [];
        foreach (glob(__DIR__ . '/../src/Cmd/*.php') as $file) {
            $source = (string) file_get_contents($file);
            if (str_contains($source, '->confirm(') && !str_contains($source, "addOption('yes'")) {
                $offenders[] = basename($file);
            }
        }

        $this->assertSame([], $offenders, 'Commands that prompt must offer --yes for one-shot use');
    }
}
