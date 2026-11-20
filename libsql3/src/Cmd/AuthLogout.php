<?php
namespace Libsql3\Cmd;

use Libsql3\Contracts\Configurable;
use Libsql3\Internal\CliStore;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AuthLogout extends Command implements Configurable
{
    use CliStore;

    protected static $defaultName = 'auth:logout';
    public array $config = [];

    public function setAppConfig(array $config)
    {
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setName('auth:logout')
            ->setDescription('Logout from the API and remove local token');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Get current logged-in user
        $username = $this->getCurrentLoggedInUser();

        if (!$username) {
            $output->writeln("<comment>No active session found. You are not logged in.</comment>");
            return 0;
        }

        $remoteFailure = null;
        $token = $this->getToken($username);

        try {
            if ($token) {
                $this->callLogoutApi($token);
            }
        } catch (\Throwable $e) {
            // Remember the failure, but do not let it abort local cleanup.
            $remoteFailure = $e->getMessage();
        } finally {
            // Local cleanup is unconditional. An unreachable or hostile server
            // must never leave a credential that still looks usable on disk.
            $this->deleteToken($username);
        }

        if ($remoteFailure !== null) {
            $output->writeln("<comment>Local session cleared for {$username}, but server revocation failed: {$remoteFailure}</comment>");
            $output->writeln('<comment>The server token may still be valid; revoke it from the admin UI.</comment>');
            return 1;
        }

        $output->writeln("<info>Successfully logged out user: {$username}</info>");
        return 0;
    }

    private function callLogoutApi(string $token): void
    {
        $request = http_request('/api/cli/logout', 'POST', ['token' => $token]);

        $httpCode = (int) ($request['status'] ?? 0);
        if ($httpCode < 200 || $httpCode > 299) {
            throw new \Exception("API returned status $httpCode");
        }
    }

    private function getToken(string $username): ?string
    {
        $stmt = $this->getPdo()->prepare("
            SELECT token 
            FROM " . config('tables.tokens') . " 
            WHERE username = :username
        ");
        $stmt->execute(['username' => $username]);
        return $stmt->fetchColumn() ?: null;
    }

    private function deleteToken(string $username): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("DELETE FROM " . config('tables.tokens') . " WHERE username = :username");
        $stmt->execute(['username' => $username]);
    }
}
