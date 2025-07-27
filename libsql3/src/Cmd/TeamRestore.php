<?php
namespace Libsql3\Cmd;

use DateTime;
use Libsql3\Internal\CliStore;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class TeamRestore extends Command
{
    use CliStore;

    protected static $defaultName = 'team:restore';
    public array $config = [];

    public function setAppConfig(array $config)
    {
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setName('team:restore')
            ->setDescription('Restore an archived team')
            ->addArgument('team_id', InputArgument::OPTIONAL, 'Team ID to restore');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');

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

            // Fetch archived teams
            $teams = $this->fetchArchivedTeams($userIdentifier, $token);

            if (empty($teams)) {
                $io->info('No archived teams found');
                return 0;
            }

            // Resolve team selection
            $teamArg = $input->getArgument('team_id');
            $team = $this->resolveTeam($teamArg, $teams, $io, $helper, $input, $output);

            // Confirm restoration
            $confirm = $io->confirm(
                "Are you sure you want to restore team <comment>'{$team['name']}'</comment>?",
                false
            );

            if (!$confirm) {
                $io->warning('Team restoration cancelled');
                return 0;
            }

            // Restore the team
            $result = $this->restoreTeam($team['id'], $userIdentifier, $token);

            if ($result['status'] !== true) {
                $error = $result['data']['message'] ?? $result['raw'];
                throw new \Exception("API error: $error");
            }

            $restoredTeam = $result['data']['team'];
            $io->success('Team restored successfully!');
            $io->table(
                ['ID', 'Name', 'Description', 'Restored At'],
                [
                    [
                        $restoredTeam['id'] ?? 'N/A',
                        $restoredTeam['name'] ?? 'Unknown',
                        $restoredTeam['description'] ?? 'Unknown',
                        (new DateTime())->format('Y-m-d H:i:s')
                    ]
                ]
            );

            return 0;

        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
            return 3;
        }
    }

    private function resolveTeam(
        ?string $teamArg,
        array $teams,
        SymfonyStyle $io,
        $helper,
        InputInterface $input,
        OutputInterface $output
    ): array {
        // Find by argument
        if ($teamArg) {
            foreach ($teams as $team) {
                if ($team['id'] === (int) $teamArg || $team['name'] === $teamArg) {
                    return $team;
                }
            }
            throw new \Exception("Team '$teamArg' not found in archived teams");
        }

        // Single team selection
        if (count($teams) === 1) {
            return $teams[0];
        }

        // Interactive selection
        $io->section('Select Archived Team');
        $io->text('Available teams:');

        $teamTable = [];
        foreach ($teams as $index => $team) {
            $teamTable[] = [
                $index + 1,
                $team['name'],
                $team['id'],
                (new DateTime($team['deleted_at']))->format('Y-m-d')
            ];
        }

        $io->table(
            ['#', 'Name', 'ID', 'Archived At'],
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
                    return $teams[$index - 1];
                }
            }

            foreach ($teams as $team) {
                if ($team['id'] === $value || $team['name'] === $value) {
                    return $team;
                }
            }

            throw new \RuntimeException('Invalid team selection');
        });

        return $helper->ask($input, $output, $question);
    }

    private function fetchArchivedTeams(string $userIdentifier, string $token): array
    {
        $request = http_request('/api/cli/team/archives', 'GET', null, [
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

    private function restoreTeam(string $teamId, string $userIdentifier, string $token): array
    {
        $request = http_request("/api/cli/team/restore/$teamId", 'POST', null, [
            "Content-Type: application/json",
            "X-User-Identifier: $userIdentifier",
            "Authorization: Bearer $token"
        ]);

        return json_decode($request['raw'], true);
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
