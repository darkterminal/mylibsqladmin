<?php
namespace Libsql3\Cmd;

use Libsql3\Internal\CliStore;
use Libsql3\Internal\Endpoint;
use Libsql3\Internal\ShellTokenProxy;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class DbShell extends Command
{
    use CliStore;

    protected static $defaultName = 'db:shell';

    protected function configure(): void
    {
        $this
            ->setName('db:shell')
            ->setDescription('Open interactive shell for libSQL database')
            ->addArgument('database_name', InputArgument::REQUIRED, 'Database name')
            ->addOption(
                'install-turso',
                null,
                InputOption::VALUE_NONE,
                'Explicitly allow installing the Turso CLI when it is missing'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $dbName = (string) $input->getArgument('database_name');
            Endpoint::assertValidDatabaseName($dbName);

            $storedEndpoint = config_get('LIBSQL_DATABASE_ENDPOINT');
            $endpoint = Endpoint::parse(
                ($storedEndpoint !== null && trim($storedEndpoint) !== '') ? $storedEndpoint : '',
                'LIBSQL_DATABASE_ENDPOINT'
            );
            warn_if_insecure_endpoint($endpoint->value, 'LIBSQL_DATABASE_ENDPOINT');

            $port = $endpoint->port ?? ($endpoint->scheme === 'https' ? 443 : 80);
            $url = $endpoint->scheme . '://' . $dbName . '.' . $endpoint->host . ':' . $port;

            // Get current user identifier from environment
            $userIdentifier = $this->getCurrentLoggedInUser() ?: null;

            if (!$userIdentifier) {
                $output->writeln('<error>There is no active session</error>');
                $output->writeln('<comment>Loggin first using auth:login <username> command</comment>');
                return 1;
            }

            // Get authentication token
            $token = $this->getTokenForUser($userIdentifier);

            if (!$token) {
                $output->writeln('<error>No valid authentication token found</error>');
                $output->writeln('<comment>Please login first using auth:login command</comment>');
                return 2;
            }

            // Resolve the executable BEFORE minting a credential. Without an
            // explicit opt-in, a missing binary must never trigger an install
            // or leave a freshly minted token unused.
            $tursoPath = $this->getTursoPath();

            if (!$tursoPath) {
                $optIn = $input->getOption('install-turso')
                    || getenv('LIBSQL3_ALLOW_TURSO_INSTALL') === '1';

                if (!$optIn) {
                    $output->writeln('<error>Turso CLI not found.</error>');
                    $output->writeln('<comment>Install it yourself, then retry:</comment>');
                    $output->writeln('  curl -sSfL https://get.tur.so/install.sh | bash');
                    $output->writeln(
                        '<comment>Or rerun with --install-turso '
                        . '(or LIBSQL3_ALLOW_TURSO_INSTALL=1).</comment>'
                    );
                    return 3;
                }

                $output->writeln('<info>Turso CLI not found. Installing with explicit opt-in...</info>');
                $this->installTurso($output);
                $tursoPath = $this->getTursoPath();

                if (!$tursoPath) {
                    throw new \RuntimeException('Turso CLI installation failed');
                }

                $output->writeln("\n<info>Turso CLI installed successfully!</info>");
            }

            if (!is_executable($tursoPath)) {
                throw new \RuntimeException("Turso CLI is not executable: {$tursoPath}");
            }

            // Get database authentication token
            $authToken = $this->getDatabaseToken($dbName, $userIdentifier, $token);

            $proxy = null;
            try {
                if ($authToken !== null && $authToken !== '') {
                    // The token never reaches the child argv. It lives in a
                    // private file read only by the loopback injector.
                    $proxy = $this->startTokenProxy($authToken, $url);
                }

                // Run Turso shell with a token-free URL.
                $output->writeln("<info>libsql3 for MylibSQLAdmin</info>");
                $output->writeln("<info>Opening Turso database shell for: {$this->getSafeUrl($url)}</info>");

                $this->runTursoShell($tursoPath, $url, $output, $proxy?->proxyUrl());

                return 0;
            } finally {
                // Always stop the injector and delete the credential, including
                // when the shell exits with an error or the operator interrupts.
                $proxy?->stop();
            }
        } catch (\Exception $e) {
            $output->writeln("<error>Error: " . $e->getMessage() . "</error>");
            return 1;
        }
    }

    private function getDatabaseToken(string $databaseName, string $userIdentifier, string $token): ?string
    {
        $request = http_request('/api/cli/db/token/mint/' . rawurlencode($databaseName), 'POST', null, [
            "Authorization: Bearer " . $token,
        ]);

        $parsed = parse_database_token_mint_response($request);

        if (!$parsed['ok']) {
            throw new \RuntimeException($parsed['error']);
        }

        return $parsed['token'];
    }


    /**
     * Protected so tests can inject a proxy handle without forking a process.
     */
    protected function startTokenProxy(string $token, string $upstreamUrl): ?ShellTokenProxy
    {
        return ShellTokenProxy::start($token, $upstreamUrl);
    }

    private function getSafeUrl(string $url): string
    {
        // Remove auth token from URL for safe display
        return preg_replace('/([?&])authToken=[^&]+(&|$)/', '$1', $url);
    }

    /**
     * Protected so tests can simulate a missing or stubbed executable.
     */
    protected function getTursoPath(): ?string
    {
        $paths = [
            shell_exec('command -v turso'),
            getenv('HOME') . '/.turso/turso'
        ];

        foreach ($paths as $path) {
            if ($path && file_exists(trim($path))) {
                return trim($path);
            }
        }

        return null;
    }

    private function installTurso(OutputInterface $output): void
    {
        $installCommand = 'curl -sSfL https://get.tur.so/install.sh | bash';
        $process = Process::fromShellCommandline($installCommand);
        $process->setTimeout(300);
        $process->run(function ($type, $buffer) use ($output) {
            $output->write($buffer);
        });

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }

    /**
     * Protected so tests can assert the exact child command without launching it.
     */
    protected function buildTursoCommand(string $tursoPath, string $url, ?string $proxyUrl): string
    {
        $command = "{$tursoPath} db shell " . escapeshellarg($url);

        if ($proxyUrl !== null) {
            $command .= ' --proxy ' . escapeshellarg($proxyUrl);
        }

        return $command;
    }

    /**
     * Protected so tests can capture the child command without launching it.
     */
    protected function runTursoShell(
        string $tursoPath,
        string $url,
        OutputInterface $output,
        ?string $proxyUrl = null
    ): void {
        $descriptorSpec = [
            0 => STDIN,
            1 => STDOUT,
            2 => STDERR
        ];

        $process = proc_open($this->buildTursoCommand($tursoPath, $url, $proxyUrl), $descriptorSpec, $pipes);

        if (!is_resource($process)) {
            throw new \RuntimeException('Failed to open Turso shell');
        }

        $status = proc_get_status($process);
        while ($status['running']) {
            usleep(100000); // 100ms
            $status = proc_get_status($process);
        }

        $exitCode = proc_close($process);
        if ($exitCode !== 0) {
            throw new \RuntimeException("Turso shell exited with code {$exitCode}");
        }
    }
}
