<?php
namespace Libsql3\Cmd;

use DateTime;
use Libsql3\Internal\CliStore;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class GroupList extends Command
{
    use CliStore;

    protected static $defaultName = 'group:list';
    public array $config = [];

    public function setAppConfig(array $config)
    {
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setName('group:list')
            ->setDescription('List all available groups')
            ->addOption('sort', 's', InputOption::VALUE_REQUIRED, 'Sort groups by id, name, created_at, or updated_at', 'updated_at');
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

            // Fetch groups from API
            $groups = $this->fetchGroups($userIdentifier, $token);

            if (empty($groups)) {
                $io->info('No groups found');
                return 0;
            }

            // Display groups in a table
            $this->renderGroupTable($io, $groups, $input->getOption('sort'));

            return 0;
        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
            return 3;
        }
    }

    private function fetchGroups(string $userIdentifier, string $token): array
    {
        $pathUrl = '/api/cli/group/lists';

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

        if (!isset($body['data']['groups']) || !is_array($body['data']['groups'])) {
            throw new \Exception("Invalid API response format");
        }

        return $body['data']['groups'];
    }

    private function renderGroupTable(SymfonyStyle $io, array $groups, string $sort): void
    {
        $io->title('Available Groups');

        $tableData = [];
        foreach ($groups as $group) {
            $tableData[] = [
                $group['id'] ?? 'N/A',
                $group['name'] ?? 'Unknown',
                $group['members_count'] ?? 0,
                (new DateTime($group['created_at']))->format('Y-m-d H:i:s') ?? 'Unknown',
                (new DateTime($group['updated_at']))->format('Y-m-d H:i:s') ?? 'Unknown',
            ];
        }

        if ($sort === 'id') {
            usort($tableData, function ($a, $b) {
                return $a[0] <=> $b[0];
            });
        } elseif ($sort === 'name') {
            usort($tableData, function ($a, $b) {
                return strnatcasecmp($a[1], $b[1]);
            });
        } elseif ($sort === 'created_at') {
            usort($tableData, function ($a, $b) {
                return $a[3] <=> $b[3];
            });
        } elseif ($sort === 'updated_at') {
            usort($tableData, function ($a, $b) {
                return $a[4] <=> $b[4];
            });
        }

        $io->table(
            ['ID', 'Name', 'Members Count', 'Created At', 'Updated At'],
            $tableData
        );

        $io->text(sprintf('Total groups: <comment>%d</comment>', count($groups)) . PHP_EOL);
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

