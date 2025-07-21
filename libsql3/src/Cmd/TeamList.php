<?php
namespace Libsql3\Cmd;

use DateTime;
use Libsql3\Internal\CliStore;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TeamList extends Command
{
    use CliStore;

    protected static $defaultName = 'team:list';
    public array $config = [];

    public function setAppConfig(array $config)
    {
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setName('team:list')
            ->setDescription('List all available teams');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Get current user identifier
        $userIdentifier = $this->getCurrentLoggedInUser() ?: null;

        if (!$userIdentifier) {
            $io->error('There is no active session');
            $io->text('Login first using: auth:login <username>');
            return 1;
        }

        try {
            // Get authentication token
            $token = $this->getTokenForUser($userIdentifier);

            if (!$token) {
                $io->error('No valid authentication token found');
                $io->text('Please login first using: auth:login');
                return 2;
            }

            // Fetch teams from API
            $teams = $this->fetchTeams($userIdentifier, $token);

            if (empty($teams)) {
                $io->info('No teams found');
                return 0;
            }

            // Display teams in a table
            $this->renderTeamTable($io, $teams);

            return 0;
        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
            return 3;
        }
    }

    private function fetchTeams(string $userIdentifier, string $token): array
    {
        $pathUrl = '/api/cli/team/lists';

        $request = http_request($pathUrl, 'GET', null, [
            "X-User-Identifier: $userIdentifier",
            "Authorization: Bearer $token"
        ]);

        $response = $request['raw'];
        $httpCode = $request['status'];

        if ($httpCode !== 200) {
            throw new \Exception("API returned status $httpCode: " . substr($response, 0, 200));
        }

        $body = json_decode($response, true);

        if (!isset($body['data']['teams']) || !is_array($body['data']['teams'])) {
            throw new \Exception("Invalid API response format");
        }

        return $body['data']['teams'];
    }

    private function renderTeamTable(SymfonyStyle $io, array $teams): void
    {
        $io->title('Available Teams');

        $tableData = [];
        foreach ($teams as $team) {
            $tableData[] = [
                $team['id'] ?? 'N/A',
                $team['name'] ?? 'Unknown',
                $team['description'] ?? 'Unknown',
                (new DateTime($team['created_at']))->format('Y-m-d H:i:s') ?? 'Unknown',
                (new DateTime($team['updated_at']))->format('Y-m-d H:i:s') ?? 'Unknown',
            ];
        }

        $io->table(
            ['ID', 'Name', 'Description', 'Created At', 'Updated At'],
            $tableData
        );

        $io->text(sprintf('Total teams: <comment>%d</comment>', count($teams)) . PHP_EOL);
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
}
