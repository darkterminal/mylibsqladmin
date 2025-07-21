<?php
namespace Libsql3\Cmd;

use Psy\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ConfigDelete extends Command
{
    protected static $defaultName = 'config:delete';

    protected function configure(): void
    {
        $this->setName('config:delete')
            ->setDescription('Delete a configuration value')
            ->addArgument('key', InputArgument::REQUIRED, 'Configuration key');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $key = $input->getArgument('key');

        try {
            $db = use_database();
            $table = config('tables.config');

            $stmt = $db->prepare("DELETE FROM $table WHERE key = :key");
            $stmt->execute(['key' => $key]);

            if ($stmt->rowCount() > 0) {
                $io->success("Configuration deleted: $key");
                return 0;
            }

            $io->warning("Configuration key '$key' not found");
            return 1;

        } catch (\Exception $e) {
            $io->error("Error deleting configuration: " . $e->getMessage());
            return 2;
        }
    }
}
