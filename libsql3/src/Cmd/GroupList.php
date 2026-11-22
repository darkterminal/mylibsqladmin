<?php
namespace Libsql3\Cmd;

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

        // An unknown --sort used to fall through to unsorted output with exit 0.
        // Reject it up front so the caller can tell a typo from a valid listing.
        $sort = $input->getOption('sort');
        $allowedSorts = ['id', 'name', 'created_at', 'updated_at'];
        if (!is_string($sort) || !in_array($sort, $allowedSorts, true)) {
            $io->error('Invalid --sort value. Allowed values: ' . implode(', ', $allowedSorts));
            return 4;
        }

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
            $this->renderGroupTable($io, $groups, $sort);

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
            "Authorization: Bearer $token"
        ]);

        $response = $request['raw'];
        $httpCode = $request['status'];

        if ($httpCode !== 200) {
            throw new \Exception("API returned status $httpCode: " . api_error_message($response));
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
                format_api_date($group['created_at'] ?? null),
                format_api_date($group['updated_at'] ?? null),
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

}

