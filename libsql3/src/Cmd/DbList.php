<?php
namespace Libsql3\Cmd;

use Libsql3\Internal\CliStore;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableSeparator;

class DbList extends Command
{
    use CliStore;

    protected static $defaultName = 'db:list';
    public array $config = [];

    public function setAppConfig(array $config)
    {
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setName('db:list')
            ->setDescription('List databases accessible to the current user');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Get current user identifier from environment
        $userIdentifier = $this->getCurrentLoggedInUser() ?: null;

        if (!$userIdentifier) {
            $output->writeln('<error>There is no active session</error>');
            $output->writeln('<comment>Loggin first using auth:login <username> command</comment>');
            return 1;
        }

        try {
            // Get authentication token
            $token = $this->getTokenForUser($userIdentifier);

            if (!$token) {
                $output->writeln('<error>No valid authentication token found</error>');
                $output->writeln('<comment>Please login first using auth:login command</comment>');
                return 2;
            }

            // Fetch databases from API
            $databases = $this->fetchDatabases($userIdentifier, $token);

            if (empty($databases)) {
                $output->writeln('<info>No databases found for user: ' . $userIdentifier . '</info>');
                return 0;
            }

            // Display results in a table
            $this->renderDatabaseTable($output, $databases);

            return 0;
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return 3;
        }
    }

    private function getTokenForUser(string $userIdentifier): ?string
    {
        // Try to get token from local store
        $token = $this->getToken($userIdentifier);

        if ($token) {
            return $token;
        }

        // Fallback to environment variables
        $envVars = ['TURSO_AUTH', 'AUTH_TOKEN', 'TOKEN'];
        foreach ($envVars as $var) {
            $token = getenv($var);
            if ($token) {
                return $token;
            }
        }

        return null;
    }

    private function fetchDatabases(string $userIdentifier, string $token): array
    {
        $apiUrl = 'http://localhost:8000/api/cli/db/lists';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $token,
            'X-User-Identifier: ' . $userIdentifier,
            'Accept: application/json',
            'X-Request-Source: CLI',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception("API returned status $httpCode: $response");
        }

        $data = json_decode($response, true);

        if (!isset($data['databases']) || !is_array($data['databases'])) {
            throw new \Exception("Invalid API response format");
        }

        return $data['databases'];
    }

    private function renderDatabaseTable(OutputInterface $output, array $databases): void
    {
        $user = $this->getCurrentLoggedInUser() ?: 'Unknown';
        $output->writeln("\n<info>Databases for $user</info>");

        $table = new Table($output);
        $table->setHeaders(['Name', 'Type', 'Group', 'Owner', 'Created At']);

        foreach ($databases as $db) {
            $table->addRow([
                $db['database_name'] ?? 'N/A',
                $db['is_schema'] ? 'Schema' : 'Database',
                $db['group_name'] ?? 'Default',
                $db['owner'] ?? 'Unknown',
                $db['created_at'] ?? 'Unknown'
            ]);
        }

        $table->render();
        $output->writeln("<comment>" . count($databases) . " databases found</comment>\n");
    }
}
