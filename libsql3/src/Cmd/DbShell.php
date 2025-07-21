<?php
namespace Libsql3\Cmd;

use Libsql3\Internal\CliStore;
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
            ->addArgument('database_name', InputArgument::REQUIRED, 'Database name');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $dbName = $input->getArgument('database_name');
        $dbEndpoint = parse_url(config_get('LIBSQL_DATABASE_ENDPOINT'));
        $url = $dbEndpoint['scheme'] . '://' . $dbName . '.' . $dbEndpoint['host'] . ':' . $dbEndpoint['port'];
        $tursoPath = $this->getTursoPath();

        // Get current user identifier from environment
        $userIdentifier = $this->getCurrentLoggedInUser() ?: null;

        if (!$userIdentifier) {
            $output->writeln('<error>There is no active session</error>');
            $output->writeln('<comment>Loggin first using auth:login <username> command</comment>');
            return 1;
        }

        try {

            // Get authentication token
            $token = $this->getTokenForUser($userIdentifier);

            if (!$token) {
                $output->writeln('<error>No valid authentication token found</error>');
                $output->writeln('<comment>Please login first using auth:login command</comment>');
                return 2;
            }

            // Install Turso CLI if not found
            if (!$tursoPath) {
                $output->writeln('<info>Turso CLI not found. Installing now...</info>');
                $this->installTurso($output);
                $tursoPath = $this->getTursoPath();

                if (!$tursoPath) {
                    throw new \RuntimeException('Turso CLI installation failed');
                }

                $output->writeln("\n<info>Turso CLI installed successfully!</info>");
            }

            // Get database authentication token
            $authToken = $this->getDatabaseToken($dbName, $userIdentifier, $token);

            if ($authToken) {
                $url = $this->addAuthTokenToUrl($url, $authToken);
                $source = 'command line';
            }

            // Run Turso shell
            $output->writeln("<info>libsql3 for MylibSQLAdmin</info>");
            $output->writeln("<info>Opening Turso database shell for: {$this->getSafeUrl($url)}</info>");

            if ($authToken) {
                $output->writeln("<comment>Using authentication token from {$source}</comment>");
            }

            $this->runTursoShell($tursoPath, $url, $output);

            return 0;
        } catch (\Exception $e) {
            $output->writeln("<error>Error: " . $e->getMessage() . "</error>");
            return 1;
        }
    }

    private function getDatabaseToken(string $databaseName, string $userIdentifier, string $token): ?string
    {
        $request = http_request('/api/cli/db/token/' . urlencode($databaseName), 'GET', null, [
            "Authorization: Bearer " . $token,
            "X-User-Identifier: " . $userIdentifier
        ]);

        $response = $request['raw'];
        $httpCode = $request['status'];

        if ($httpCode !== 200) {
            throw new \Exception("API returned status $httpCode: " . substr($response, 0, 200));
        }

        $body = json_decode($response, true);

        return $body['data']['token'];
    }

    private function getTokenForUser(string $userIdentifier): ?string
    {
        // Try to get token from local store
        $token = $this->getToken($userIdentifier);

        if ($token) {
            return $token;
        }

        // Fallback to environment variables
        $envVars = ['TURSO_AUTH', 'AUTH_TOKEN', 'TOKEN'];
        foreach ($envVars as $var) {
            $token = getenv($var);
            if ($token) {
                return $token;
            }
        }

        return null;
    }

    private function addAuthTokenToUrl(string $url, string $token): string
    {
        // Parse URL to handle existing query parameters
        $parsedUrl = parse_url($url);
        $query = [];

        if (isset($parsedUrl['query'])) {
            parse_str($parsedUrl['query'], $query);
        }

        // Add or replace authToken parameter
        $query['authToken'] = $token;

        // Rebuild URL with new query parameters
        $scheme = isset($parsedUrl['scheme']) ? $parsedUrl['scheme'] . '://' : '';
        $host = $parsedUrl['host'] ?? '';
        $port = isset($parsedUrl['port']) ? ':' . $parsedUrl['port'] : '';
        $user = isset($parsedUrl['user']) ? $parsedUrl['user'] : '';
        $pass = isset($parsedUrl['pass']) ? ':' . $parsedUrl['pass'] : '';
        $pass = ($user || $pass) ? "$pass@" : '';
        $path = $parsedUrl['path'] ?? '';
        $query = '?' . http_build_query($query);
        $fragment = isset($parsedUrl['fragment']) ? '#' . $parsedUrl['fragment'] : '';

        return "$scheme$user$pass$host$port$path$query$fragment";
    }

    private function getSafeUrl(string $url): string
    {
        // Remove auth token from URL for safe display
        return preg_replace('/([?&])authToken=[^&]+(&|$)/', '$1', $url);
    }

    private function getTursoPath(): ?string
    {
        // Check if turso is in system PATH
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

    private function runTursoShell(string $tursoPath, string $url, OutputInterface $output): void
    {
        echo $url . PHP_EOL;
        // Start interactive shell
        $descriptorSpec = [
            0 => STDIN,
            1 => STDOUT,
            2 => STDERR
        ];

        $process = proc_open("{$tursoPath} db shell " . escapeshellarg($url), $descriptorSpec, $pipes);

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

