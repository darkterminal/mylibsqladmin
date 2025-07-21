<?php
namespace Libsql3\Cmd;

use Libsql3\Internal\CliStore;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class DbRestore extends Command
{
    use CliStore;

    protected static $defaultName = 'db:restore';
    public array $config = [];

    public function setAppConfig(array $config)
    {
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setName('db:restore')
            ->setDescription('Restore a deleted database from archive')
            ->addArgument('database_name', InputArgument::REQUIRED, 'Database name to restore')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force restore without confirmation');
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
            // Confirm restoration unless forced
            if (!$input->getOption('force')) {
                $confirmation = $io->confirm(
                    "Are you sure you want to restore database <comment>'$dbName'</comment> from deleted database?",
                    false
                );

                if (!$confirmation) {
                    $io->warning('Database restoration cancelled');
                    return 0;
                }
            }

            // Restore database
            $result = $this->restoreDatabase($dbName, $userIdentifier, $token);

            if ($result['status'] === 200) {
                $io->success("Database '$dbName' restored successfully!");
                return 0;
            }

            throw new \Exception("API returned status {$result['status']}: " . substr($result['raw'], 0, 200));
        } catch (\Exception $e) {
            $io->error('Database restoration failed: ' . $e->getMessage());
            return 3;
        }
    }

    private function restoreDatabase(string $dbName, $userIdentifier, string $token): array
    {
        $request = http_request('/api/cli/db/restore/' . urlencode($dbName), 'POST', null, [
            "Authorization: Bearer $token",
            "X-User-Identifier: $userIdentifier",
            "Content-Type: application/json"
        ]);

        return [
            'status' => $request['status'],
            'raw' => $request['raw']
        ];
    }

    private function getTokenForUser(string $userIdentifier): ?string
    {
        $token = $this->getToken($userIdentifier);
        if ($token) {
            return $token;
        }

        $envVars = ['TURSO_AUTH', 'AUTH_TOKEN', 'TOKEN'];
        foreach ($envVars as $var) {
            $token = getenv($var);
            if ($token) {
                return $token;
            }
        }

        return null;
    }
}
