<?php
namespace Libsql3\Cmd;

use Psy\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ConfigSet extends Command
{
    protected static $defaultName = 'config:set';

    protected function configure(): void
    {
        $this
            ->setName('config:set')
            ->setDescription('Set a configuration value (locally)')
            ->addArgument('key', InputArgument::REQUIRED, 'Configuration key')
            ->addArgument('value', InputArgument::REQUIRED, 'Configuration value');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $key = $input->getArgument('key');
        $value = $input->getArgument('value');

        try {
            config_set($key, $value);
            $io->success("Configuration set: $key = $value");
            return 0;
        } catch (\Exception $e) {
            $io->error("Error setting configuration: " . $e->getMessage());
            return 1;
        }
    }
}
