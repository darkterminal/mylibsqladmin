<?php

namespace Libsql3\Cmd;

use Libsql3\Internal\CliStore;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;

class TeamEdit extends Command
{
    use CliStore;

    protected static $defaultName = 'team:edit';
    public array $config = [];

    public function setAppConfig(array $config)
    {
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setName('team:edit')
            ->setDescription('Edit an existing team')
            ->addArgument('id', InputArgument::REQUIRED, 'Team ID')
            ->addOption('name', null, InputOption::VALUE_OPTIONAL, 'New team name')
            ->addOption('description', null, InputOption::VALUE_OPTIONAL, 'New team description');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
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
            $teamId = $input->getArgument('id');

            // Fetch current team details
            $currentTeam = $this->fetchTeam($teamId, $userIdentifier, $token);
            $io->title("Editing Team: {$currentTeam['name']}");

            // Display current details
            $io->table(
                ['Field', 'Current Value'],
                [
                    ['ID', $currentTeam['id']],
                    ['Name', $currentTeam['name']],
                    ['Description', $currentTeam['description'] ?? 'None'],
                    ['Created At', $currentTeam['created_at']],
                    ['Updated At', $currentTeam['updated_at']]
                ]
            );

            // Collect changes
            $data = [];
            $helper = $this->getHelper('question');

            // Handle name
            if ($name = $input->getOption('name')) {
                $data['name'] = $name;
            } else {
                $nameQuestion = new Question(
                    "Enter new name [current: <comment>{$currentTeam['name']}</comment>]: ",
                    $currentTeam['name']
                );
                $nameQuestion->setTrimmable(true);
                /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
                $newName = $helper->ask($input, $output, $nameQuestion);

                if ($newName !== $currentTeam['name']) {
                    $data['name'] = $newName;
                }

                if (empty($data['name'])) {
                    $data['name'] = $currentTeam['name'];
                }
            }

            // Handle description
            $currentDescription = $currentTeam['description'] ?? null;
            if ($desc = $input->getOption('description')) {
                $data['description'] = $desc;
            } else {
                $descQuestion = new Question(
                    "Enter new description [current: <comment>" . ($currentDescription ?: 'None') . "</comment>]: ",
                    $currentDescription
                );
                $descQuestion->setTrimmable(true);
                $newDesc = $helper->ask($input, $output, $descQuestion);

                if ($newDesc !== $currentDescription) {
                    $data['description'] = $newDesc;
                }

                if (empty($data['description'])) {
                    $data['description'] = $currentDescription;
                }
            }

            // Check if any changes were made
            if (empty($data)) {
                $io->info('No changes made. Team remains unchanged.');
                return 0;
            }

            // Send update request
            $request = http_request("/api/cli/team/update/$teamId", 'PUT', $data, [
                "Content-Type: application/json",
                "X-User-Identifier: $userIdentifier",
                "Authorization: Bearer $token"
            ]);

            $status = $request['status'];
            $response = json_decode($request['raw'], true);

            if ($status !== 200) {
                $error = $response['message'] ?? $request['raw'];
                throw new \Exception("API error ($status): $error");
            }

            $teamData = $response['data']['team'];
            $io->success("Team '{$teamData['name']}' updated successfully!");

            $io->table(
                ['Field', 'Updated Value'],
                [
                    ['ID', $teamData['id']],
                    ['Name', $teamData['name']],
                    ['Description', $teamData['description'] ?? 'None'],
                    ['Updated At', $teamData['updated_at']]
                ]
            );

            return 0;
        } catch (\Exception $e) {
            $io->error('Team update failed: ' . $e->getMessage());
            return 3;
        }
    }

    private function fetchTeam(string $teamId, string $userIdentifier, string $token): array
    {
        $request = http_request("/api/cli/team/get/$teamId", 'GET', null, [
            "X-User-Identifier: $userIdentifier",
            "Authorization: Bearer $token"
        ]);

        $status = $request['status'];
        $response = json_decode($request['raw'], true);

        if ($status !== 200) {
            $error = $response['message'] ?? $request['raw'];
            throw new \Exception("Failed to fetch team: $error");
        }

        if (!isset($response['data']['team'])) {
            throw new \Exception("Invalid team data in response");
        }

        return $response['data']['team'];
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
