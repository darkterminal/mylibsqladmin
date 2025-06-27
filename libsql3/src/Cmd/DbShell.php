<?php
namespace Libsql3\Cmd;

use Psy\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\ProcessFailedException;

class DbShell extends Command
{
    protected static $defaultName = 'db:shell';

    protected function configure(): void
    {
        $this
            ->setName('db:shell')
            ->setDescription('Open interactive shell for Turso database')
            ->addArgument('url', InputArgument::REQUIRED, 'Turso database URL');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $url = $input->getArgument('url');
        $tursoPath = $this->getTursoPath();

        try {
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

            // Check for auth tokens in environment variables
            $authToken = $this->getAuthToken();
            if ($authToken) {
                $url = $this->addAuthTokenToUrl($url, $authToken);
            }

            // Run Turso shell
            $output->writeln("<info>libsql3 for MylibSQLAdmin</info>");
            $output->writeln("<info>Opening Turso database shell for: {$this->getSafeUrl($url)}</info>");

            if ($authToken) {
                $output->writeln("<comment>Using authentication token from environment</comment>");
            }

            $this->runTursoShell($tursoPath, $url, $output);

            return 0;
        } catch (\Exception $e) {
            $output->writeln("<error>Error: " . $e->getMessage() . "</error>");
            return 1;
        }
    }

    private function getAuthToken(): ?string
    {
        // Check environment variables in order of priority
        $envVars = ['TURSO_AUTH', 'AUTH_TOKEN', 'TOKEN'];

        foreach ($envVars as $var) {
            $token = getenv($var);
            if ($token !== false && !empty($token)) {
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
