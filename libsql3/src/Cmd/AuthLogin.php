<?php
namespace Libsql3\Cmd;

use Libsql3\Contracts\Configurable;
use Libsql3\Internal\AuthContext;
use Libsql3\Internal\CliStore;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;

class AuthLogin extends Command implements Configurable
{
    use CliStore;

    protected static $defaultName = 'auth:login';
    public array $config = [];

    /** User whose server token was revoked by this invocation, if any. */
    private ?string $revokedUser = null;

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
            ->addOption(
                'force',
                null,
                InputOption::VALUE_NONE,
                'Revoke the current server token before logging in again'
            )
            ->addOption(
                'token',
                null,
                InputOption::VALUE_REQUIRED,
                'Pre-obtained plaintext API token (non-interactive login)'
            )
            ->addOption(
                'expires-at',
                null,
                InputOption::VALUE_REQUIRED,
                'Expiry for --token as ISO-8601 (default: 30 days from now)'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $username = (string) $input->getArgument('username');
        $force = (bool) $input->getOption('force');

        // Mandatory order: revoke first, authenticate second. A failed revoke
        // aborts without touching local state; a failed login after a successful
        // revoke clears the dead local credential rather than leaving it usable.
        try {
            if ($force) {
                $this->revokeCurrentToken();
            }
        } catch (\Throwable $e) {
            $output->writeln("<error>Could not revoke the current server token: {$e->getMessage()}</error>");
            $output->writeln('<comment>Local credentials were not changed.</comment>');

            return 1;
        }

        try {
            $tokenData = $this->resolveCredential($input, $output, $username, $force);
        } catch (\Throwable $e) {
            if ($this->revokedUser !== null) {
                $this->deleteToken($this->revokedUser);
                $this->clearActiveUser();
                $output->writeln('<comment>The previous server token was revoked, but the new login failed.</comment>');
                $output->writeln('<comment>Local session cleared; run auth:login again.</comment>');
            }
            $output->writeln('<error>Authentication failed: ' . $e->getMessage() . '</error>');

            return 1;
        }

        $output->writeln('<info>Authentication successful!</info>');

        if ($tokenData === 1) {
            $output->writeln(
                '<comment>You are already logged in until '
                . format_api_date($this->getExpiresAt($username)) . '.</comment>'
            );

            return 0;
        }

        try {
            $this->storeToken(
                $username,
                $tokenData['token'],
                $tokenData['expires_at'],
                $tokenData['timezone'] ?? null
            );
        } catch (\Exception $e) {
            $output->writeln('<error>Token storage failed: ' . $e->getMessage() . '</error>');

            return 2;
        }

        return 0;
    }

    /**
     * Non-interactive callers supply a pre-obtained token via --token or the
     * namespaced env var; the password question is never asked without a TTY.
     *
     * @return array{token:string, expires_at:string, timezone:?string}|int 1 means "already logged in"
     */
    private function resolveCredential(
        InputInterface $input,
        OutputInterface $output,
        string $username,
        bool $force
    ): array|int {
        $optionToken = $input->getOption('token');
        $envToken = getenv(AuthContext::TOKEN_ENV);

        $inlineToken = is_string($optionToken) && $optionToken !== ''
            ? $optionToken
            : (is_string($envToken) && $envToken !== '' ? $envToken : null);

        if ($inlineToken !== null) {
            return [
                'token' => $inlineToken,
                'expires_at' => $this->resolveInlineExpiry($input),
            ];
        }

        if (!$input->isInteractive()) {
            throw new \RuntimeException(
                'Non-interactive login requires --token or ' . AuthContext::TOKEN_ENV
                . '; the password prompt is disabled without a TTY.'
            );
        }

        return $this->authenticate($username, $this->askPassword($input, $output), $force);
    }

    private function resolveInlineExpiry(InputInterface $input): string
    {
        $option = $input->getOption('expires-at');
        if (is_string($option) && $option !== '') {
            if (strtotime($option) === false) {
                throw new \InvalidArgumentException('--expires-at must be a valid date/time');
            }

            return $option;
        }

        // The server issues 30-day tokens; a CLI-supplied token has no expiry
        // metadata of its own, so mirror that default and let operators pass
        // the real value with --expires-at when they know it.
        return date('Y-m-d H:i:s', time() + (30 * 86400));
    }

    private function askPassword(InputInterface $input, OutputInterface $output): string
    {
        /** @var \Symfony\Component\Console\Helper\QuestionHelper $helper */
        $helper = $this->getHelper('question');
        $question = new Question('Enter password: ');
        $question->setHidden(true);
        $question->setHiddenFallback(false);
        $password = $helper->ask($input, $output, $question);

        if (!is_string($password) || $password === '') {
            throw new \RuntimeException('A password is required.');
        }

        return $password;
    }

    /**
     * Revoke the token behind the current local session. No local row is
     * touched here: the caller decides what to do on success or failure.
     */
    private function revokeCurrentToken(): void
    {
        $current = $this->getCurrentLoggedInUser();
        if ($current === null) {
            return;
        }
        $token = $this->getToken($current);
        if ($token === null) {
            return;
        }

        revoke_cli_token($token);
        $this->revokedUser = $current;
    }

    private function authenticate(string $username, string $password, bool $force = false): array|int
    {
        // Check if already logged in. --force deliberately bypasses this so a
        // valid cached token does not block rotation.
        if (!$force && $this->authCheck($username)) {
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
        $timezone = $body['data']['timezone'] ?? null;

        return [
            'token' => $body['data']['token'],
            'expires_at' => $body['data']['expires_at'],
            'timezone' => is_valid_display_timezone($timezone) ? $timezone : null,
        ];
    }
}
