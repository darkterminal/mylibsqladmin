<?php
namespace Libsql3\Cmd;

use Libsql3\Contracts\Configurable;
use Libsql3\Internal\CliStore;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;

class DbArchive extends Command implements Configurable
{
    use CliStore;

    protected static $defaultName = 'db:archive';
    public array $config = [];

    public function setAppConfig(array $config)
    {
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setName('db:archive')
            ->setDescription('List all archived databases accessible to the current user');
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
        $pathUrl = '/api/cli/db/archives';

        $request = http_request($pathUrl, 'GET', null, [
            "X-User-Identifier: $userIdentifier",
            "Authorization: Bearer $token"
        ]);

        $response = $request['raw'];
        $httpCode = $request['status'];

        if ($httpCode !== 200) {
            throw new \Exception("API returned status $httpCode: $response");
        }

        $body = json_decode($response, true);

        if (!isset($body['data']['databases']) || !is_array($body['data']['databases'])) {
            throw new \Exception("Invalid API response format");
        }

        return $body['data']['databases'];
    }

    private function renderDatabaseTable(OutputInterface $output, array $databases): void
    {
        $user = $this->getCurrentLoggedInUser() ?: 'Unknown';
        $output->writeln("\n<info>Archived Databases for $user</info>");

        $table = new Table($output);
        $table->setHeaders(['Name', 'Type', 'Group', 'Owner', 'Created At', 'Delete At']);

        foreach ($databases as $db) {
            $table->addRow([
                $db['database_name'] ?? 'N/A',
                $db['is_schema'] === '0' ? 'standalone' : ('1' === $db['is_schema'] ? 'schema database' : "child of [" . $db['is_schema'] . "]"),
                $db['group_name'] ?? 'default',
                $db['owner'] ?? 'Unknown',
                $db['created_at'] ?? 'Unknown',
                $db['deleted_at'] ?? 'Unknown',
            ]);
        }

        $table->render();
        $output->writeln("<comment>" . count($databases) . " databases found</comment>\n");
    }
}
