<?php
namespace Libsql3\Cmd;

use Libsql3\Internal\CliStore;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TeamCreate extends Command
{
    use CliStore;

    protected static $defaultName = 'team:create';
    public array $config = [];

    public function setAppConfig(array $config)
    {
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setName('team:create')
            ->setDescription('Create a new team')
            ->addArgument('name', InputArgument::REQUIRED, 'Team name')
            ->addOption('description', 'd', InputOption::VALUE_OPTIONAL, 'Team description');
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
            $data = [
                'name' => $input->getArgument('name'),
                'description' => $input->getOption('description') ?? ''
            ];

            $request = http_request('/api/cli/team/create', 'POST', $data, [
                "Content-Type: application/json",
                "X-User-Identifier: $userIdentifier",
                "Authorization: Bearer $token"
            ]);

            $status = $request['status'];
            $response = json_decode($request['raw'], true);

            if ($status !== 200 && $status !== 201) {
                $error = $response['message'] ?? $request['raw'];
                throw new \Exception("API error ($status): $error");
            }

            $teamData = $response['data']['team'];
            $io->success("Team '{$teamData['name']}' created successfully!");

            $io->table(
                ['ID', 'Name', 'Description', 'Created At'],
                [
                    [
                        $teamData['id'],
                        $teamData['name'],
                        $teamData['description'] ?? 'None',
                        $teamData['created_at']
                    ]
                ]
            );

            $io->note("You've been added as the owner of this team");
            return 0;
        } catch (\Exception $e) {
            $io->error('Team creation failed: ' . $e->getMessage());
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
}
