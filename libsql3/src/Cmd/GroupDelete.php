<?php
namespace Libsql3\Cmd;

use Libsql3\Internal\CliStore;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Style\SymfonyStyle;

class GroupDelete extends Command
{
    use CliStore;

    protected static $defaultName = 'group:delete';
    public array $config = [];

    public function setAppConfig(array $config)
    {
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setName('group:delete')
            ->setDescription('Delete a group')
            ->addArgument('name', InputArgument::OPTIONAL, 'Group name to delete')
            ->addOption('force', 'f', InputOption::VALUE_NONE, 'Force deletion without confirmation');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $helper = $this->getHelper('question');
        $userIdentifier = $this->getCurrentLoggedInUser() ?: null;

        if (!$userIdentifier) {
            $io->error('No active session. Login first using: auth:login');
            return 1;
        }

        $token = $this->getTokenForUser($userIdentifier);
        if (!$token) {
            $io->error('No valid authentication token');
            $io->text('Please login first using: auth:login');
            return 2;
        }

        try {
            // Fetch available groups
            $groups = $this->fetchGroups($userIdentifier, $token);
            if (empty($groups)) {
                throw new \Exception('No groups available to delete');
            }

            // Resolve group name
            $groupName = $input->getArgument('name');
            $groupId = null;

            if ($groupName) {
                // Find group by name
                foreach ($groups as $group) {
                    if ($group['name'] === $groupName) {
                        $groupId = $group['id'];
                        $io->text("Deleting group: <comment>{$group['name']}</comment> (ID: <comment>{$group['id']}</comment>)");
                        break;
                    }
                }

                if (!$groupId) {
                    throw new \Exception("Group '$groupName' not found");
                }
            } else {
                // Interactive selection
                $io->section('Select Group to Delete');
                $choices = [];
                foreach ($groups as $group) {
                    $choices[$group['id']] = "{$group['name']} (ID: {$group['id']})";
                }

                $question = new ChoiceQuestion(
                    '<question>Select a group to delete:</question> ',
                    $choices
                );
                $question->setErrorMessage('Invalid group selection: %s');

                $selected = $helper->ask($input, $output, $question);
                $groupId = array_search($selected, $choices);
                $io->text("Selected group: <comment>$selected</comment>");
            }

            // Confirm deletion
            if (!$input->getOption('force')) {
                $confirmation = $io->confirm(
                    "Are you sure you want to permanently delete this group?",
                    false
                );

                if (!$confirmation) {
                    $io->warning('Group deletion cancelled');
                    return 0;
                }
            }

            // Delete group
            $result = $this->deleteGroup($groupId, $userIdentifier, $token);

            if ($result['status'] !== true) {
                throw new \Exception("API returned status {$result['status']}: " . substr($result['raw'], 0, 200));
            }

            $io->success('Group deleted successfully!');
            return 0;

        } catch (\Exception $e) {
            $io->error('Group deletion failed: ' . $e->getMessage());
            return 3;
        }
    }

    private function deleteGroup(string $groupId, string $userIdentifier, string $token): array
    {
        $request = http_request("/api/cli/group/delete/$groupId", 'DELETE', null, [
            "X-User-Identifier: $userIdentifier",
            "Authorization: Bearer $token"
        ]);

        $status = $request['status'];
        if ($status !== 200) {
            throw new \Exception("Failed to delete group: HTTP $status");
        }

        $response = json_decode($request['raw'], true);

        return $response;
    }

    private function fetchGroups(string $userIdentifier, string $token): array
    {
        $request = http_request('/api/cli/group/lists', 'GET', null, [
            "X-User-Identifier: $userIdentifier",
            "Authorization: Bearer $token"
        ]);

        $status = $request['status'];
        $response = json_decode($request['raw'], true);

        if ($status !== 200) {
            throw new \Exception("Failed to fetch groups: " . ($response['message'] ?? "HTTP $status"));
        }

        if (empty($response['data']['groups']) || !is_array($response['data']['groups'])) {
            return [];
        }

        return $response['data']['groups'];
    }

    private function getTokenForUser(string $userIdentifier): ?string
    {
        $token = $this->getToken($userIdentifier);
        if ($token) {
            return $token;
        }

        $envVars = ['TURSO_AUTH', 'AUTH_TOKEN', 'TOKEN'];
        foreach ($envVars as $var) {
            if ($token = getenv($var)) {
                return $token;
            }
        }
        return null;
    }
}
