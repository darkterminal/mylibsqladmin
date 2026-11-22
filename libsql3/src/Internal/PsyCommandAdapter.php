<?php

declare(strict_types=1);

namespace Libsql3\Internal;

use Psy\Command\Command as PsyCommand;
use Psy\Shell;
use Symfony\Component\Console\Command\Command as SymfonyCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Exposes a PsySH command to a plain Symfony Console application.
 *
 * PsySH commands reject setApplication() unless it receives a Psy\Shell, and
 * Shell::run() replaces argv with its REPL loop. Wrapping the command keeps the
 * one-shot path additive without editing libsql3/vendor/** (D-12/D-35).
 */
final class PsyCommandAdapter extends SymfonyCommand
{
    private static ?Shell $shell = null;

    public function __construct(private readonly PsyCommand $inner)
    {
        parent::__construct((string) $inner->getName());

        // PsySH commands require a Psy\Shell as their application. Sharing one
        // shell instance satisfies that contract without entering its REPL.
        $inner->setApplication(self::shell());

        $this->setDescription($inner->getDescription());
        $this->setHelp($inner->getHelp());
        $this->setDefinition(clone $inner->getDefinition());
        $this->setHidden($inner->isHidden());

        foreach ($inner->getAliases() as $alias) {
            $this->addAlias($alias);
        }
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        return $this->inner->run($input, $output);
    }

    private static function shell(): Shell
    {
        return self::$shell ??= new Shell();
    }
}
