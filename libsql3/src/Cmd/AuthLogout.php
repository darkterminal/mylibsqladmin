<?php
namespace Libsql3\Cmd;

use Libsql3\Internal\CliStore;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class AuthLogout extends Command
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

        try {
            // Get token for the user
            $token = $this->getToken($username);

            if ($token) {
                // Try to revoke token on the server
                $this->callLogoutApi($token);
            }

            // Delete token locally regardless of API status
            $this->deleteToken($username);

            $output->writeln("<info>Successfully logged out user: {$username}</info>");
            return 0;

        } catch (\Exception $e) {
            $output->writeln("<error>Logout failed: " . $e->getMessage() . "</error>");
            return 1;
        }
    }

    private function callLogoutApi(string $token): void
    {
        $apiUrl = 'http://localhost:8000/api/cli/logout';
        $payload = json_encode(['token' => $token]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload),
            'X-Request-Source: CLI',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $message = "API returned status $httpCode";
            $responseData = json_decode($response, true);
            if (isset($responseData['message'])) {
                $message = $responseData['message'];
            }
            throw new \Exception($message);
        }
    }

    private function getToken(string $username): ?string
    {
        $stmt = $this->getPdo()->prepare("
            SELECT token 
            FROM " . $this->config['token_table'] . " 
            WHERE username = :username
        ");
        $stmt->execute(['username' => $username]);
        return $stmt->fetchColumn() ?: null;
    }

    private function deleteToken(string $username): void
    {
        $pdo = $this->getPdo();
        $stmt = $pdo->prepare("DELETE FROM " . $this->config['token_table'] . " WHERE username = :username");
        $stmt->execute(['username' => $username]);
    }
}
