<?php
namespace Libsql3\Cmd;

use Libsql3\Internal\CliStore;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class GroupCreate extends Command
{
    use CliStore;

    protected static $defaultName = 'group:create';
    public array $config = [];

    public function setAppConfig(array $config)
    {
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setName('group:create')
            ->setDescription('Create a new group')
            ->addArgument('name', InputArgument::OPTIONAL, 'Group name')
            ->addOption('team', 't', InputOption::VALUE_OPTIONAL, 'Team ID for the new group');
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
            // Fetch available teams
            $teams = $this->fetchTeams($userIdentifier, $token);
            if (empty($teams)) {
                throw new \Exception('No teams available. Please create a team first.');
            }

            // Resolve team ID
            $teamOption = $input->getOption('team');
            $teamId = $this->resolveTeam($teamOption, $teams, $io, $helper, $input, $output);

            // Get group name from argument or prompt
            $groupName = $input->getArgument('name');
            if (empty($groupName)) {
                $io->section('Create New Group');
                $groupName = $helper->ask($input, $output, new Question('<question>Enter group name:</question> '));
            }

            // Create group
            $result = $this->createGroup($teamId, $groupName, $userIdentifier, $token);

            if ($result['status'] !== true) {
                $error = $result['data']['message'] ?? $result['raw'];
                throw new \Exception("API error ({$result['status']}): $error");
            }

            $groupData = $result['data']['group'];
            $io->success('Group created successfully!');
            $io->table(
                ['Name', 'Team', 'Created At'],
                [
                    [
                        $groupData['name'],
                        $groupData['team_name'],
                        $groupData['created_at']
                    ]
                ]
            );
            return 0;

        } catch (\Exception $e) {
            $io->error('Group creation failed: ' . $e->getMessage());
            return 3;
        }
    }

    private function resolveTeam(
        ?string $teamOption,
        array $teams,
        SymfonyStyle $io,
        $helper,
        InputInterface $input,
        OutputInterface $output
    ): string {
        // Use provided team option if available
        if ($teamOption) {
            // Validate team exists
            foreach ($teams as $team) {
                if ($team['id'] === $teamOption || $team['name'] === $teamOption) {
                    $io->text("Using team: <comment>{$team['name']}</comment> (ID: <comment>{$team['id']}</comment>)");
                    return $team['id'];
                }
            }
            throw new \Exception("Team '$teamOption' not found");
        }

        // If only one team exists, use it automatically
        if (count($teams) === 1) {
            $firstTeam = $teams[0];
            $io->note("Using your only team: <comment>{$firstTeam['name']}</comment> (ID: <comment>{$firstTeam['id']}</comment>)");
            return $firstTeam['id'];
        }

        // Prompt for team selection
        $io->section('Select Team');
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

        $choices = [];
        foreach ($teams as $index => $team) {
            $choices[$index + 1] = $team['id'];
        }

        $question = new ChoiceQuestion(
            '<question>Select a team by number or ID:</question> ',
            $choices
        );
        $question->setAutocompleterValues(array_values($choices));
        $question->setValidator(function ($value) use ($teams, $choices) {
            if (is_numeric($value)) {
                $index = (int) $value;
                if (isset($choices[$index])) {
                    return $choices[$index];
                }
            }

            foreach ($teams as $team) {
                if ($team['id'] === $value || $team['name'] === $value) {
                    return $team['id'];
                }
            }

            throw new \RuntimeException('Invalid team selection');
        });

        $teamId = $helper->ask($input, $output, $question);
        $io->text("Selected team: <comment>$teamId</comment>");
        return $teamId;
    }

    private function createGroup(
        string $teamId,
        string $name,
        string $userIdentifier,
        string $token
    ): array {
        $data = [
            'name' => $name,
            'team_id' => $teamId
        ];

        $request = http_request('/api/cli/group/create', 'POST', $data, [
            "Content-Type: application/json",
            "X-User-Identifier: $userIdentifier",
            "Authorization: Bearer $token"
        ]);

        $response = json_decode($request['raw'], true);

        return $response;
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

        return $response['data']['teams'];
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
