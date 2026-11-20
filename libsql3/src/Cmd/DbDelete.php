<?php
namespace Libsql3\Cmd;

use Libsql3\Internal\CliStore;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DbDelete extends Command
{
    use CliStore;

    protected static $defaultName = 'db:delete';
    public array $config = [];

    public function setAppConfig(array $config)
    {
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setName('db:delete')
            ->setDescription('Delete a database')
            ->addArgument('database_name', InputArgument::REQUIRED, 'Database name to delete')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force deletion without confirmation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dbName = $input->getArgument('database_name');
        $userIdentifier = $this->getCurrentLoggedInUser() ?: null;

        if (!$userIdentifier) {
            $io->error('No active session. Login first using: auth:login');
            return 1;
        }

        $token = $this->getTokenForUser($userIdentifier);
        if (!$token) {
            $io->error('No valid authentication token');
            $io->text('Please login first using: auth:login');
            return 2;
        }

        try {
            // --force is the explicit intent: skip the prompt entirely.
            if ($input->getOption('force')) {
                $result = $this->forceDeleteDatabase($dbName, $userIdentifier, $token);
            } else {
                $confirmation = $io->confirm(
                    "Are you sure you want to delete database <comment>'$dbName'</comment>?",
                    false
                );

                if (!$confirmation) {
                    $io->warning('Database deletion cancelled');
                    return 4;
                }

                $result = $this->deleteDatabase($dbName, $userIdentifier, $token);
            }

            if ($result['status'] === 204 || $result['status'] === 200) {
                $io->success("Database '$dbName' deleted successfully!");
                return 0;
            }

            throw new \Exception("API returned status {$result['status']}: " . api_error_message($result['raw']));
        } catch (\Exception $e) {
            $io->error('Database deletion failed: ' . $e->getMessage());
            return 3;
        }
    }

    private function deleteDatabase(string $dbName, string $userIdentifier, string $token): array
    {
        $request = http_request('/api/cli/db/delete/' . urlencode($dbName), 'DELETE', null, [
            "Authorization: Bearer $token",
        ]);

        return [
            'status' => $request['status'],
            'raw' => $request['raw']
        ];
    }

    private function forceDeleteDatabase(string $dbName, string $userIdentifier, string $token): array
    {
        $request = http_request('/api/cli/db/force-delete/' . urlencode($dbName), 'DELETE', null, [
            "Authorization: Bearer $token",
        ]);

        return [
            'status' => $request['status'],
            'raw' => $request['raw']
        ];
    }

}
