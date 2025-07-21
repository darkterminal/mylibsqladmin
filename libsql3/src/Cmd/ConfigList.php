<?php
namespace Libsql3\Cmd;

use PDO;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class ConfigList extends Command
{
    protected static $defaultName = 'config:list';

    protected function configure(): void
    {
        $this->setName('config:list')
            ->setDescription('List all configuration values');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        try {
            $db = use_database();
            $table = config('tables.config');

            $stmt = $db->query("SELECT key, value FROM $table");
            $configs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($configs)) {
                $io->warning('No configuration values found');
                return 0;
            }

            $tableRows = [];
            foreach ($configs as $config) {
                $tableRows[] = [$config['key'], $config['value']];
            }

            $io->table(['Key', 'Value'], $tableRows);
            return 0;

        } catch (\Exception $e) {
            $io->error("Error listing configuration: " . $e->getMessage());
            return 1;
        }
    }
}
