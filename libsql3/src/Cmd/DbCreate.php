<?php
namespace Libsql3\Cmd;

use Libsql3\Internal\CliStore;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

class DbCreate extends Command
{
    use CliStore;

    protected static $defaultName = 'db:create';
    public array $config = [];

    public function setAppConfig(array $config)
    {
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setName('db:create')
            ->setDescription('Create a new database')
            ->addArgument('name', InputArgument::REQUIRED, 'Database name')
            ->addOption('team', 't', InputOption::VALUE_OPTIONAL, 'Team name or ID')
            ->addOption('group', 'g', InputOption::VALUE_OPTIONAL, 'Group name or ID')
            ->addOption('schema', null, InputOption::VALUE_NONE, 'Mark database as schema database')
            ->addOption('use-schema', null, InputOption::VALUE_OPTIONAL, 'Use / extend an existing schema database');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');
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
            // Get database name
            $dbName = $input->getArgument('name');

            // Fetch available teams
            $teams = $this->fetchTeams($userIdentifier, $token);
            if (empty($teams)) {
                throw new \Exception('No teams available. Please create a team first.');
            }

            // Resolve team interactively if needed
            $teamOption = $input->getOption('team');
            $teamId = $this->resolveTeam($teamOption, $teams, $io, $helper, $input, $output);

            // Fetch available groups
            $groups = $this->fetchGroups($userIdentifier, $token);
            if (empty($groups)) {
                throw new \Exception('No groups available. Please create a group first.');
            }

            // Resolve group interactively if needed
            $groupOption = $input->getOption('group');
            $groupId = $this->resolveGroup($groupOption, $groups, $io, $helper, $input, $output);

            // Resolve is database is schema
            $isSchema = $input->getOption('schema');

            // Resolve use schema option
            $useSchemaOption = $input->getOption('use-schema');
            if ($useSchemaOption) {
                $useSchema = $useSchemaOption;
            }

            $dbSchema = $useSchema ?? $isSchema;

            $data = [
                'name' => $dbName,
                'team_id' => $teamId,
                'group_id' => $groupId,
                'is_schema' => $dbSchema
            ];

            $request = http_request('/api/cli/db/create', 'POST', $data, [
                "Content-Type: application/json",
                "X-User-Identifier: $userIdentifier",
                "Authorization: Bearer $token"
            ]);

            $status = $request['status'];
            $response = json_decode($request['raw'], true);

            if ($status !== 201) {
                $error = $response['message'] ?? $request['raw'];
                throw new \Exception("API error ($status): $error");
            }

            $dbData = $response['data']['database'];
            $io->success("Database '{$dbData['name']}' created successfully!");

            $io->table(
                ['ID', 'Name', 'Team', 'Group', 'Schema', 'Created At'],
                [
                    [
                        $dbData['id'],
                        $dbData['name'],
                        $dbData['team_name'] ?? $dbData['team_id'],
                        $dbData['group_name'] ?? $dbData['group_id'],
                        $dbData['is_schema'] === true ? 'schema' : ($dbData['is_schema'] === false ? 'standalone' : $dbData['is_schema']),
                        $dbData['created_at']
                    ]
                ]
            );

            return 0;
        } catch (\Exception $e) {
            $io->error('Database creation failed: ' . $e->getMessage());
            return 3;
        }
    }

    private function resolveTeam(
        $teamOption,
        array $teams,
        SymfonyStyle $io,
        $helper,
        InputInterface $input,
        OutputInterface $output
    ): string {
        // Convert to string if it's integer
        if (is_int($teamOption)) {
            $teamOption = (string) $teamOption;
        }

        // Use provided team option if available
        if ($teamOption) {
            // Try to find team by ID or name
            $found = false;
            foreach ($teams as $team) {
                if ($team['id'] === $teamOption || $team['name'] === $teamOption) {
                    $io->text("Using team: <comment>{$team['name']}</comment> (ID: <comment>{$team['id']}</comment>)");
                    return $team['id'];
                }
            }
            throw new \Exception("Team '$teamOption' not found");
        }

        // Check config for default team
        $defaultTeam = $this->config['DEFAULT_TEAM'] ?? null;
        if ($defaultTeam) {
            // Find default team by ID or name
            foreach ($teams as $team) {
                if ($team['id'] === $defaultTeam || $team['name'] === $defaultTeam) {
                    $io->text("Using configured default team: {$team['name']} (ID: {$team['id']})");
                    return $team['id'];
                }
            }
            throw new \Exception("Default team '$defaultTeam' is not available");
        }

        // If we only have one team, use it automatically
        if (count($teams) === 1) {
            $firstTeam = $teams[0];
            $io->text("Using your only team: <comment>{$firstTeam['name']}</comment> (ID: <comment>{$firstTeam['id']}</comment>)");
            return $firstTeam['id'];
        }

        // Format team choices with better visual presentation
        $io->section('Team Selection');
        $io->text('Available teams:');

        $teamTable = [];
        foreach ($teams as $index => $team) {
            $teamTable[] = [
                $index + 1,
                $team['name'],
                $team['id']
            ];
        }

        $io->table(
            ['#', 'Name', 'ID'],
            $teamTable
        );

        // Create choice question
        $choices = [];
        foreach ($teams as $index => $team) {
            $choices[$index + 1] = $team['id'];
        }

        $question = new ChoiceQuestion(
            '<question>Select a team by number or ID:</question> ',
            $choices
        );
        $question->setAutocompleterValues(array_values($choices));
        $question->setErrorMessage('Invalid team selection: %s');
        $question->setValidator(function ($value) use ($teams, $choices) {
            // Check if input is a number
            if (is_numeric($value)) {
                $index = (int) $value;
                if (isset($choices[$index])) {
                    return $choices[$index];
                }
            }

            // Check if input is a valid team ID
            if (in_array($value, $choices, true)) {
                return $value;
            }

            // Check if input is a team name
            foreach ($teams as $team) {
                if ($team['name'] === $value) {
                    return $team['id'];
                }
            }

            throw new \RuntimeException('Please select a valid team number, ID, or name');
        });

        $teamId = $helper->ask($input, $output, $question);
        $io->text("Selected team: <comment>$teamId</comment>");
        return $teamId;
    }

    private function resolveGroup(
        $groupOption,
        array $groups,
        SymfonyStyle $io,
        $helper,
        InputInterface $input,
        OutputInterface $output
    ): string {
        // Convert to string if it's integer
        if (is_int($groupOption)) {
            $groupOption = (string) $groupOption;
        }

        // Use provided group option if available
        if ($groupOption) {
            // Try to find group by ID or name
            $found = false;
            foreach ($groups as $group) {
                if ($group['id'] === $groupOption || $group['name'] === $groupOption) {
                    $io->text("Using group: <comment>{$group['name']}</comment> (ID: <comment>{$group['id']}</comment>)");
                    return $group['id'];
                }
            }
            throw new \Exception("Group '$groupOption' not found");
        }

        // Check config for default group
        $defaultGroup = $this->config['DEFAULT_GROUP'] ?? null;
        if ($defaultGroup) {
            // Find default group by ID or name
            foreach ($groups as $group) {
                if ($group['id'] === $defaultGroup || $group['name'] === $defaultGroup) {
                    $io->text("Using configured default group: {$group['name']} (ID: {$group['id']})");
                    return $group['id'];
                }
            }
            throw new \Exception("Default group '$defaultGroup' is not available");
        }

        // If we only have one group, use it automatically
        if (count($groups) === 1) {
            $firstGroup = $groups[0];
            $io->text("Using your only group: <comment>{$firstGroup['name']}</comment> (ID: <comment>{$firstGroup['id']}</comment>)");
            return $firstGroup['id'];
        }

        // Format group choices with better visual presentation
        $io->section('Group Selection');
        $io->text('Available groups:');

        $groupTable = [];
        foreach ($groups as $index => $group) {
            $groupTable[] = [
                $index + 1,
                $group['name'],
                $group['id']
            ];
        }

        $io->table(
            ['#', 'Name', 'ID'],
            $groupTable
        );

        // Create choice question
        $choices = [];
        foreach ($groups as $index => $group) {
            $choices[$index + 1] = $group['id'];
        }

        $question = new ChoiceQuestion(
            '<question>Select a group by number or ID:</question> ',
            $choices
        );
        $question->setAutocompleterValues(array_values($choices));
        $question->setErrorMessage('Invalid group selection: %s');
        $question->setValidator(function ($value) use ($groups, $choices) {
            // Check if input is a number
            if (is_numeric($value)) {
                $index = (int) $value;
                if (isset($choices[$index])) {
                    return $choices[$index];
                }
            }

            // Check if input is a valid group ID
            if (in_array($value, $choices, true)) {
                return $value;
            }

            // Check if input is a group name
            foreach ($groups as $group) {
                if ($group['name'] === $value) {
                    return $group['id'];
                }
            }

            throw new \RuntimeException('Please select a valid group number, ID, or name');
        });

        $groupId = $helper->ask($input, $output, $question);
        $io->text("Selected group: <comment>$groupId</comment>");
        return $groupId;
    }

    private function fetchTeams(string $userIdentifier, string $token): array
    {
        $request = http_request('/api/cli/team/lists', 'GET', null, [
            "X-User-Identifier: $userIdentifier",
            "Authorization: Bearer $token"
        ]);

        $status = $request['status'];
        $response = json_decode($request['raw'], true);

        if ($status !== 200) {
            throw new \Exception("Failed to fetch teams: " . ($response['message'] ?? "HTTP $status"));
        }

        if (empty($response['data']['teams']) || !is_array($response['data']['teams'])) {
            return [];
        }

        // Sort teams by creation date (oldest first)
        usort($response['data']['teams'], function ($a, $b) {
            return strtotime($a['created_at']) - strtotime($b['created_at']);
        });

        return $response['data']['teams'];
    }

    private function fetchGroups(string $userIdentifier, string $token): array
    {
        $request = http_request('/api/cli/group/lists', 'GET', null, [
            "X-User-Identifier: $userIdentifier",
            "Authorization: Bearer $token"
        ]);

        $status = $request['status'];
        $response = json_decode($request['raw'], true);

        if ($status !== 200) {
            throw new \Exception("Failed to fetch groups: " . ($response['message'] ?? "HTTP $status"));
        }

        if (empty($response['data']['groups']) || !is_array($response['data']['groups'])) {
            return [];
        }

        return $response['data']['groups'];
    }

    private function getTokenForUser(string $userIdentifier): ?string
    {
        $token = $this->getToken($userIdentifier);
        if ($token) {
            return $token;
        }

        $envVars = ['TURSO_AUTH', 'AUTH_TOKEN', 'TOKEN'];
        foreach ($envVars as $var) {
            if ($token = getenv($var)) {
                return $token;
            }
        }
        return null;
    }
}
