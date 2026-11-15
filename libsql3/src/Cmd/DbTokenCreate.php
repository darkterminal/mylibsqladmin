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
                '/api/cli/db/token/mint/' . urlencode($databaseName),
                'POST',
                null,
                [
                    "Authorization: Bearer $token",
                    'Content-Type: application/json',
                ]
            );

            if ($request['status'] !== 201 && $request['status'] !== 200) {
                $body = json_decode((string) $request['raw'], true);
                $message = $body['message'] ?? substr((string) $request['raw'], 0, 200);
                $output->writeln("<error>API returned status {$request['status']}: $message</error>");
                return 3;
            }

            $body = json_decode((string) $request['raw'], true);
            $plaintext = $body['data']['token'] ?? null;
            $fingerprint = $body['data']['fingerprint'] ?? null;

            if (!$plaintext) {
                $output->writeln('<error>Malformed response from server</error>');
                return 4;
            }

            $output->writeln('<info>Full-access token minted for</info> ' . $databaseName);
            $output->writeln($plaintext);
            $output->writeln('<comment>Fingerprint: ' . ($fingerprint ?? 'unknown') . '</comment>');
            $output->writeln('<comment>Store this token now — it will never be shown again.</comment>');

            return 0;
        } catch (\Exception $e) {
            $output->writeln('<error>Error: ' . $e->getMessage() . '</error>');
            return 5;
        }
    }

    private function getTokenForUser(string $userIdentifier): ?string
    {
        $token = $this->getToken($userIdentifier);

        if ($token) {
            return $token;
        }

        foreach (['TURSO_AUTH', 'AUTH_TOKEN', 'TOKEN'] as $var) {
            $token = getenv($var);
            if ($token) {
                return $token;
            }
        }

        return null;
    }
}
