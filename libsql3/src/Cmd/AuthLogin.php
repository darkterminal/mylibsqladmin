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
            ->addArgument('username', InputArgument::REQUIRED, 'Your username');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Get username
        $username = $input->getArgument('username');

        // Get password securely
        $password = null;
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

        // Endpoints are operator-owned configuration. The old behavior overwrote
        // them on every login and fabricated the DB endpoint from app_url with
        // a fragile ':8000' -> ':8080' rewrite. app_url is still returned by the
        // platform for other consumers, but the CLI must not derive endpoints
        // from it.
        return [
            'token' => $body['data']['token'],
            'expires_at' => $body['data']['expires_at']
        ];
    }
}
