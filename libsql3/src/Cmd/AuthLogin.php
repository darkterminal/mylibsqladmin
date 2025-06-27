<?php
namespace Libsql3\Cmd;

use Libsql3\Internal\CliStore;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class AuthLogin extends Command
{
    use CliStore;

    protected static $defaultName = 'auth:login';
    public array $config = [];

    public function setAppConfig(array $config)
    {
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setName('auth:login')
            ->setDescription('Authenticate and store API token')
            ->addArgument('username', InputArgument::REQUIRED, 'Your username')
            ->addArgument('password', InputArgument::OPTIONAL, 'Your password (will be hidden)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Get username
        $username = $input->getArgument('username');

        // Get password securely
        $password = $input->getArgument('password');
        if (null === $password) {
            $helper = $this->getHelper('question');
            $question = new Question('Enter password: ');
            $question->setHidden(true);
            $question->setHiddenFallback(false);
            $password = $helper->ask($input, $output, $question);
        }

        // Authenticate with API
        try {
            $tokenData = $this->authenticate($username, $password);
            $output->writeln("<info>Authentication successful!</info>");
            if ($tokenData === 1) {
                $expiresAt = new \DateTime($this->getExpiresAt($username));
                $output->writeln("<comment>You are already logged in until {$expiresAt->format('Y-m-d H:i:s')}.</comment>");
                return 0;
            }
        } catch (\Exception $e) {
            $output->writeln("<error>Authentication failed: " . $e->getMessage() . "</error>");
            return 1;
        }

        // Store token in database
        try {
            $this->storeToken($username, $tokenData['token'], $tokenData['expires_at']);
        } catch (\Exception $e) {
            $output->writeln("<error>Token storage failed: " . $e->getMessage() . "</error>");
            return 2;
        }

        return 0;
    }

    private function authenticate(string $username, string $password): array|int
    {
        // Check if already logged in
        if ($this->authCheck($username)) {
            return 1;
        }

        $apiUrl = 'http://localhost:8000/api/cli/login';
        $payload = json_encode(['username' => $username, 'password' => $password]);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'Content-Length: ' . strlen($payload)
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception("API returned status $httpCode");
        }

        $data = json_decode($response, true);
        if (!isset($data['token']) || !isset($data['expires_at'])) {
            throw new \Exception("Invalid API response");
        }

        return [
            'token' => $data['token'],
            'expires_at' => $data['expires_at']
        ];
    }
}
