<?php
namespace Libsql3\Cmd;

use Libsql3\Contracts\Configurable;
use Libsql3\Internal\CliStore;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class AuthLogin extends Command implements Configurable
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
            /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
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

        $pathUrl = '/api/cli/login';
        $payload = ['username' => $username, 'password' => $password];

        $request = http_request($pathUrl, 'POST', $payload);

        if ($request['status'] !== 200) {
            throw new \Exception("API returned status {$request['status']}");
        }

        $body = json_decode($request['raw'], true);
        if (!isset($body['data']['token']) || !isset($body['data']['expires_at'])) {
            throw new \Exception("Invalid API response");
        }

        if (isset($body['data']['app_url'])) {
            config_set('LIBSQL_API_ENDPOINT', $body['data']['app_url']);

            if (str_contains($body['data']['app_url'], 'localhost')) {
                $databaseEndpoint = str_replace('8000', '8080', $body['data']['app_url']);
                config_set('LIBSQL_DATABASE_ENDPOINT', $databaseEndpoint);
            } else {
                config_set('LIBSQL_DATABASE_ENDPOINT', "{$body['data']['app_url']}:8080");
            }
        }

        return [
            'token' => $body['data']['token'],
            'expires_at' => $body['data']['expires_at']
        ];
    }
}
