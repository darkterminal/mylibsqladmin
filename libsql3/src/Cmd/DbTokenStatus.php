<?php
namespace Libsql3\Cmd;

use Libsql3\Internal\CliStore;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * db:token:status <database_name> — report whether a database has a token.
 *
 * The platform endpoint returns only a masked, hash-derived fingerprint. This
 * command never displays or reconstructs raw token material.
 */
class DbTokenStatus extends Command
{
    use CliStore;

    protected static $defaultName = 'db:token:status';

    protected function configure(): void
    {
        $this
            ->setName('db:token:status')
            ->setDescription('Show whether a database has an access token (fingerprint only)')
            ->addArgument('database_name', InputArgument::REQUIRED, 'Database name');
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

        $databaseName = (string) $input->getArgument('database_name');

        try {
            $request = http_request(
                '/api/cli/db/token/' . urlencode($databaseName),
                'GET',
                null,
                ["Authorization: Bearer $token"]
            );

            if (($request['status'] ?? 0) !== 200) {
                throw new \RuntimeException('API returned status ' . ($request['status'] ?? 0));
            }

            $body = json_decode((string) ($request['raw'] ?? ''), true);
            if (!is_array($body) || ($body['status'] ?? null) !== 'success' || !is_array($body['data'] ?? null)) {
                throw new \RuntimeException('Invalid API response format');
            }

            $fingerprint = $body['data']['token'] ?? null;
            if (!is_string($fingerprint) || $fingerprint === '') {
                $io->info("No token exists for database '$databaseName'.");
                return 0;
            }

            $io->success("A token exists for database '$databaseName'.");
            $io->text('Fingerprint: ' . $fingerprint);
            $io->text('Use db:token:create <database_name> to mint a fresh plaintext token.');
            return 0;
        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
            return 3;
        }
    }
}
