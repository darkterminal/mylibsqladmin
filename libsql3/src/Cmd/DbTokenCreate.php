<?php
namespace Libsql3\Cmd;

use Libsql3\Contracts\Configurable;
use Libsql3\Internal\CliStore;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * db:token:create <database> — mint a fresh full-access token for a database.
 *
 * The plaintext is printed exactly once and never stored client-side. The
 * server stores only the SHA-256 hash of the token.
 */
class DbTokenCreate extends Command implements Configurable
{
    use CliStore;

    protected static $defaultName = 'db:token:create';
    public array $config = [];

    public function setAppConfig(array $config)
    {
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setName('db:token:create')
            ->setDescription('Mint a fresh full-access token for a database (shown once, never stored)')
            ->addArgument('database', InputArgument::REQUIRED, 'Database name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $userIdentifier = $this->getCurrentLoggedInUser() ?: null;

        if (!$userIdentifier) {
            $output->writeln('<error>There is no active session</error>');
            $output->writeln('<comment>Login first using auth:login <username> command</comment>');
            return 1;
        }

        $databaseName = (string) $input->getArgument('database');

        try {
            $token = $this->getTokenForUser($userIdentifier);

            if (!$token) {
                $output->writeln('<error>No valid authentication token found</error>');
                return 2;
            }

            $request = http_request(
                '/api/cli/db/token/mint/' . rawurlencode($databaseName),
                'POST',
                null,
                [
                    "Authorization: Bearer $token",
                    'Content-Type: application/json',
                ]
            );

            $parsed = parse_database_token_mint_response($request);

            if (!$parsed['ok']) {
                $output->writeln("<error>API returned status {$parsed['status']}: {$parsed['error']}</error>");
                return $parsed['kind'] === 'malformed' ? 4 : 3;
            }

            $output->writeln('<info>Full-access token minted for</info> ' . $databaseName);
            $output->writeln($parsed['token']);
            $output->writeln('<comment>Fingerprint: ' . ($parsed['fingerprint'] ?? 'unknown') . '</comment>');
            $output->writeln('<comment>Store this token now — it will never be shown again.</comment>');

            return 0;
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return 5;
        }
    }

}
