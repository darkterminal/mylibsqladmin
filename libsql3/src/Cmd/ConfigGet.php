<?php
namespace Libsql3\Cmd;

use Psy\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ConfigGet extends Command
{
    protected static $defaultName = 'config:get';

    protected function configure(): void
    {
        $this
            ->setName('config:get')
            ->setDescription('Get a configuration value (locally)')
            ->addArgument('key', InputArgument::REQUIRED, 'Configuration key');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $key = $input->getArgument('key');

        try {
            $value = config_get($key);

            if ($value === null) {
                $io->warning("Configuration key '$key' not found");
                return 1;
            }

            $io->text($value);
            return 0;
        } catch (\Exception $e) {
            $io->error("Error getting configuration: " . $e->getMessage());
            return 2;
        }
    }
}
