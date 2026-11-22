<?php
namespace Libsql3\Cmd;

use DateTime;
use Libsql3\Internal\CliStore;
use Psy\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Question\ChoiceQuestion;
use Symfony\Component\Console\Question\Question;

class TeamRestore extends Command
{
    use CliStore;

    protected static $defaultName = 'team:restore';
    public array $config = [];

    public function setAppConfig(array $config)
    {
        $this->config = $config;
    }

    protected function configure(): void
    {
        $this
            ->setName('team:restore')
            ->setDescription('Restore an archived team')
            ->addArgument('team_id', InputArgument::OPTIONAL, 'Team ID to restore')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Deprecated alias of --yes (confirm without a prompt)')
            ->addOption('yes', null, InputOption::VALUE_NONE, 'Confirm the restore prompt without asking (one-shot)');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        // A non-interactive run never prompts, so it must not require a helper
        // set just to construct the command.
        $helper = $input->isInteractive() ? $this->getHelper('question') : null;

        // Without a TTY both the team picker and the confirmation prompt are
        // unavailable. Demand explicit values instead of silently declining.
        if (!$input->isInteractive()) {
            $teamArg = $input->getArgument('team_id');
            if ($teamArg === null || $teamArg === '') {
                $io->error('Non-interactive mode requires a team_id argument; prompts are disabled.');
                return 4;
            }
            if (!$input->getOption('force') && !$input->getOption('yes')) {
                $io->error('Non-interactive restore requires --yes to confirm the operation.');
                return 4;
            }
        }

        // D-44: --yes is the only prompt acknowledgement going forward. This
        // command shipped with --force before --yes existed, so keep it as a
        // compatibility alias but say so at runtime instead of leaving two
        // flags that look equally canonical.
        if ($input->getOption('force')) {
            $io->text('<comment>--force is deprecated for team:restore; use --yes to acknowledge the confirmation.</comment>');
        }

        // Get current user identifier
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

            // Fetch archived teams
            $teams = $this->fetchArchivedTeams($userIdentifier, $token);

            if (empty($teams)) {
                $io->info('No archived teams found');
                return 0;
            }

            // Resolve team selection
            $teamArg = $input->getArgument('team_id');
            $team = $this->resolveTeam($teamArg, $teams, $io, $helper, $input, $output);

            // Confirm restoration. --force/--yes are explicit non-interactive
            // acknowledgements; without a TTY a declined prompt must not look
            // like a successful no-op (F-52).
            $confirm = ($input->getOption('force') || $input->getOption('yes')) ? true : $io->confirm(
                "Are you sure you want to restore team <comment>'{$team['name']}'</comment>?",
                false
            );

            if (!$confirm) {
                $io->warning('Team restoration cancelled');
                return 0;
            }

            // Restore the team
            $result = $this->restoreTeam($team['id'], $userIdentifier, $token);

            if (!isset($result['status']) || $result['status'] !== true) {
                $error = $result['data']['message'] ?? $result['message'] ?? null;
                if (!is_string($error) || $error === '') {
                    $error = 'Unexpected response from server';
                }
                throw new \Exception("API error: $error");
            }

            if (!isset($result['data']['team']) || !is_array($result['data']['team'])) {
                throw new \Exception('Invalid API response format');
            }

            $restoredTeam = $result['data']['team'];
            $io->success('Team restored successfully!');
            $io->table(
                ['ID', 'Name', 'Description', 'Restored At'],
                [
                    [
                        $restoredTeam['id'] ?? 'N/A',
                        $restoredTeam['name'] ?? 'Unknown',
                        $restoredTeam['description'] ?? 'Unknown',
                        (new DateTime())->format('Y-m-d H:i:s')
                    ]
                ]
            );

            return 0;

        } catch (\Exception $e) {
            $io->error('Error: ' . $e->getMessage());
            return 3;
        }
    }

    private function resolveTeam(
        ?string $teamArg,
        array $teams,
        SymfonyStyle $io,
        $helper,
        InputInterface $input,
        OutputInterface $output
    ): array {
        // Find by argument
        if ($teamArg) {
            foreach ($teams as $team) {
                if ($team['id'] === (int) $teamArg || $team['name'] === $teamArg) {
                    return $team;
                }
            }
            throw new \Exception("Team '$teamArg' not found in archived teams");
        }

        // Single team selection
        if (count($teams) === 1) {
            return $teams[0];
        }

        // Interactive selection
        $io->section('Select Archived Team');
        $io->text('Available teams:');

        $teamTable = [];
        foreach ($teams as $index => $team) {
            $teamTable[] = [
                $index + 1,
                $team['name'],
                $team['id'],
                format_api_date($team['deleted_at'] ?? null, 'Y-m-d')
            ];
        }

        $io->table(
            ['#', 'Name', 'ID', 'Archived At'],
            $teamTable
        );

        $choices = [];
        foreach ($teams as $index => $team) {
            $choices[$index + 1] = $team['id'];
        }

        $question = new ChoiceQuestion(
            '<question>Select a team by number or ID:</question> ',
            $choices
        );
        $question->setAutocompleterValues(array_values($choices));
        $question->setValidator(function ($value) use ($teams, $choices) {
            if (is_numeric($value)) {
                $index = (int) $value;
                if (isset($choices[$index])) {
                    return $teams[$index - 1];
                }
            }

            foreach ($teams as $team) {
                if ($team['id'] === $value || $team['name'] === $value) {
                    return $team;
                }
            }

            throw new \RuntimeException('Invalid team selection');
        });

        $selected = $helper->ask($input, $output, $question);
        if (!is_array($selected)) {
            throw new \RuntimeException('No archived team selected; pass team_id as an argument.');
        }

        return $selected;
    }

    private function fetchArchivedTeams(string $userIdentifier, string $token): array
    {
        $request = http_request('/api/cli/team/archives', 'GET', null, [
            "Authorization: Bearer $token"
        ]);

        $response = $request['raw'] ?? '';
        $httpCode = $request['status'] ?? 0;

        if ($httpCode !== 200) {
            throw new \Exception("API returned status $httpCode: " . api_error_message($response));
        }

        $body = json_decode($response, true);

        if (!isset($body['data']['teams']) || !is_array($body['data']['teams'])) {
            throw new \Exception("Invalid API response format");
        }

        return $body['data']['teams'];
    }

    private function restoreTeam(string $teamId, string $userIdentifier, string $token): array
    {
        $request = http_request("/api/cli/team/restore/$teamId", 'POST', null, [
            "Content-Type: application/json",
            "Authorization: Bearer $token"
        ]);

        return json_decode($request['raw'], true);
    }

}
