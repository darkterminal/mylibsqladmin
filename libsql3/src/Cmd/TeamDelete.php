<?php
namespace Libsql3\Cmd;

use Libsql3\Internal\CliStore;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class TeamDelete extends Command
{
    use CliStore;

    protected static $defaultName = 'team:delete';
    public array $config = [];

    public function setAppConfig(array $config)
    {
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setName('team:delete')
            ->setDescription('Delete a team')
            ->addArgument('id', InputArgument::REQUIRED, 'Team ID')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force deletion without confirmation');
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
            $team = $this->fetchTeam($teamId, $userIdentifier, $token);

            // Confirm deletion unless forced
            if (!$input->getOption('force') && !$io->confirm("Are you sure you want to permanently delete team '{$team['name']}'?", false)) {
                $io->warning('Deletion cancelled');
                return 0;
            }

            $request = http_request("/api/cli/team/delete/$teamId", 'DELETE', null, [
                "X-User-Identifier: $userIdentifier",
                "Authorization: Bearer $token"
            ]);

            $status = $request['status'];
            $response = json_decode($request['raw'], true);

            if ($status !== 204 && $status !== 200) {
                $error = $response['message'] ?? $request['raw'];
                throw new \Exception("API error ($status): $error");
            }

            $io->success("Team '$teamId' deleted successfully!");
            return 0;
        } catch (\Exception $e) {
            $io->error('Team deletion failed: ' . $e->getMessage());
            return 3;
        }
    }

    private function getTokenForUser(string $userIdentifier): ?string
    {
        // Token handling same as TeamCreate
        $token = $this->getToken($userIdentifier);
        if ($token)
            return $token;

        $envVars = ['TURSO_AUTH', 'AUTH_TOKEN', 'TOKEN'];
        foreach ($envVars as $var) {
            if ($token = getenv($var))
                return $token;
        }
        return null;
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
}
