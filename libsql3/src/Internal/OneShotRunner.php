<?php

declare(strict_types=1);

namespace Libsql3\Internal;

use Libsql3\App;
use Libsql3\Version;
use Symfony\Component\Console\Input\ArgvInput;

/**
 * Additive argv adapter for non-interactive use.
 *
 * D-12: the PsySH REPL remains the default when no arguments are passed. This
 * runner is only entered when the entrypoint receives explicit arguments, and
 * it never edits vendor code or changes the REPL configuration.
 */
final class OneShotRunner
{
    public static function isOneShot(array $argv): bool
    {
        $args = array_slice($argv, 1);

        return $args !== [] && ($args[0] ?? '') !== 'repl';
    }

    /**
     * @param list<string> $argv
     */
    public static function run(array $argv): int
    {
        $args = array_slice($argv, 1);

        // Version must work without a configured endpoint: it is metadata, not
        // an application command. D-14 keeps this string sourced from one place.
        if (in_array('--version', $args, true) || in_array('-V', $args, true)) {
            fwrite(STDOUT, 'libsql3 ' . Version::CURRENT . PHP_EOL);

            return 0;
        }

        $app = new App();
        $app->setAutoExit(false);
        foreach ($app->getLibsqlCommands() as $command) {
            $app->add(new PsyCommandAdapter($command));
        }

        $input = new ArgvInput($argv);
        // One-shot calls are for scripts; never block on a question helper.
        $input->setInteractive(false);

        return $app->run($input);
    }
}
