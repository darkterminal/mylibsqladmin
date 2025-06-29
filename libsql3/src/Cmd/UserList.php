<?php
namespace Libsql3\Cmd;

use Libsql3\Internal\CliStore;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Style\SymfonyStyle;

class UserList extends Command
{
    use CliStore;

    protected static $defaultName = 'user:list';
    public array $config = [];

    public function setAppConfig(array $config)
    {
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setName('user:list')
            ->setDescription('List all users (superadmin only)')
            ->addOption(
                'show-email',
                's',
                InputOption::VALUE_NONE,
                'Show full email addresses'
            )
            ->addOption(
                'page',
                'p',
                InputOption::VALUE_REQUIRED,
                'Page number',
                1
            )
            ->addOption(
                'per-page',
                'l',
                InputOption::VALUE_REQUIRED,
                'Number of items per page',
                10
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        // Get current user identifier from environment
        $userIdentifier = $this->getCurrentLoggedInUser() ?: null;

        if (!$userIdentifier) {
            $io->error('There is no active session');
            $io->text('Login first using: auth:login <username>');
            return 1;
        }

        try {
            // Get authentication token
            $token = $this->getTokenForUser($userIdentifier);

            if (!$token) {
                $io->error('No valid authentication token found');
                $io->text('Please login first using: auth:login');
                return 2;
            }

            // Fetch users from API
            $users = $this->fetchUsers($userIdentifier, $token);

            if (empty($users)) {
                $io->info('No users found');
                return 0;
            }

            // Handle pagination
            $page = max(1, (int) $input->getOption('page'));
            $perPage = max(1, (int) $input->getOption('per-page'));
            $totalUsers = count($users);
            $totalPages = ceil($totalUsers / $perPage);

            // Validate page number
            if ($page > $totalPages) {
                $page = max(1, $totalPages);
            }

            // Paginate results
            $offset = ($page - 1) * $perPage;
            $paginatedUsers = array_slice($users, $offset, $perPage);

            // Display results
            $this->renderUserTable($io, $paginatedUsers, $input->getOption('show-email'));

            // Show pagination info
            $io->text(sprintf(
                "Page <comment>%d/%d</comment> | Users <comment>%d-%d</comment> of <comment>%d</comment>",
                $page,
                $totalPages,
                $offset + 1,
                min($offset + $perPage, $totalUsers),
                $totalUsers
            ));

            return 0;
        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
            return 3;
        }
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

    private function fetchUsers(string $userIdentifier, string $token): array
    {
        $apiUrl = 'http://localhost:8000/api/cli/user/lists';

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $token",
            "X-User-Identifier: $userIdentifier",
            'Accept: application/json',
            'X-Request-Source: CLI',
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        if ($httpCode !== 200) {
            throw new \Exception("API returned status $httpCode: " . substr($response, 0, 100));
        }

        $data = json_decode($response, true);

        if (!isset($data['users']) || !is_array($data['users'])) {
            throw new \Exception("Invalid API response format");
        }

        return $data['users'];
    }

    private function renderUserTable(SymfonyStyle $io, array $users, bool $showEmail = false): void
    {
        $io->title('System Users');

        $table = new Table($io);
        $table->setHeaders(['#', 'Username', 'Name', 'Email', 'Roles', 'Created At']);

        foreach ($users as $index => $user) {
            $roles = implode(', ', $user['roles'] ?? []);

            // Mask email unless show-email flag is set
            $email = $user['email'] ?? '';
            if ($email && !$showEmail) {
                $email = $this->maskEmail($email);
            }

            $table->addRow([
                $index + 1,
                $user['username'] ?? 'Unknown',
                $user['name'] ?? '',
                $email,
                $roles,
                $user['created_at'] ?? 'Unknown'
            ]);
        }

        $table->render();

        if (!$showEmail) {
            $io->text('<comment>Use --show-email option to display email addresses</comment>');
        }
    }

    private function maskEmail(string $email): string
    {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return '****';
        }

        list($local, $domain) = explode('@', $email);
        $maskedLocal = substr($local, 0, 1) . '****' . substr($local, -1);
        return $maskedLocal . '@' . $domain;
    }
}
