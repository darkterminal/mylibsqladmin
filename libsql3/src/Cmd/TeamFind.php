<?php
namespace Libsql3\Cmd;

use Libsql3\Internal\CliStore;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * team:find <team_name> — resolve a team name to its stable fields.
 *
 * D-07: the platform endpoint already exists and is tested; this command makes
 * it reachable instead of deleting it.
 */
class TeamFind extends Command
{
    use CliStore;

    protected static $defaultName = 'team:find';

    protected function configure(): void
    {
        $this
            ->setName('team:find')
            ->setDescription('Find a team by name and show its id')
            ->addArgument('team_name', InputArgument::REQUIRED, 'Team name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $userIdentifier = $this->getCurrentLoggedInUser() ?: null;

        if (!$userIdentifier) {
            $io->error('There is no active session');
            $io->text('Login first using: auth:login <username>');
            return 1;
        }

        $token = $this->getToken($userIdentifier);
        if (!$token) {
            $io->error('No valid authentication token found');
            return 2;
        }

        $teamName = (string) $input->getArgument('team_name');

        try {
            $request = http_request(
                '/api/cli/team/find/' . rawurlencode($teamName),
                'GET',
                null,
                ["Authorization: Bearer $token"]
            );

            if (($request['status'] ?? 0) !== 200) {
                throw new \RuntimeException('API returned status ' . ($request['status'] ?? 0));
            }

            $body = json_decode((string) ($request['raw'] ?? ''), true);
            if (!is_array($body) || ($body['status'] ?? null) !== 'success' || !is_array($body['data']['team'] ?? null)) {
                throw new \RuntimeException('Invalid API response format');
            }

            $team = $body['data']['team'];
            $io->success('Team found successfully!');
            $io->table(
                ['ID', 'Name', 'Description'],
                [[
                    $team['id'] ?? '',
                    $team['name'] ?? '',
                    $team['description'] ?? '',
                ]]
            );

            return 0;
        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
            return 3;
        }
    }
}
