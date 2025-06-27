<?php
namespace Libsql3\Cmd;

use Libsql3\Internal\CliStore;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class WhoAmI extends Command
{
    use CliStore;
    protected static $defaultName = 'whoami';
    public array $config = [];

    public function setAppConfig(array $config)
    {
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setName('whoami')
            ->setDescription('Display the current user');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $user = $this->getCurrentLoggedInUser();
        if (!$user) {
            $output->writeln("<comment>No active session found. You are not logged in.</comment>");
            return 0;
        }
        $output->writeln("You are: <info>{$user}</info>");
        return 0;
    }
}
