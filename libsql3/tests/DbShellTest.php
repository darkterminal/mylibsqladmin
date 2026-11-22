<?php

declare(strict_types=1);

namespace Libsql3\Tests;

use Libsql3\Cmd\DbShell;
use Libsql3\Internal\HttpTransport;
use Libsql3\Internal\ShellTokenProxy;
use PDO;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

final class StubDbShell extends DbShell
{
    /** @var array{tursoPath:string, url:string, proxyUrl:?string, command:string, tokenFile:?string, directory:?string} */
    public array $captured = [];
    public bool $tursoMissing = false;
    public bool $shellFailure = false;
    private ?ShellTokenProxy $proxy = null;

    protected function getTursoPath(): ?string
    {
        return $this->tursoMissing ? null : '/bin/sh';
    }

    protected function startTokenProxy(string $token, string $upstreamUrl): ?ShellTokenProxy
    {
        // Exercise the real credential-file lifecycle without forking a process.
        [$directory, $tokenFile] = ShellTokenProxy::writeTokenFile($token);
        $this->proxy = new ShellTokenProxy(45999, $tokenFile, $directory, 0);
        return $this->proxy;
    }

    protected function runTursoShell(
        string $tursoPath,
        string $url,
        OutputInterface $output,
        ?string $proxyUrl = null
    ): void {
        $this->captured = [
            'tursoPath' => $tursoPath,
            'url' => $url,
            'proxyUrl' => $proxyUrl,
            'command' => $this->buildTursoCommand($tursoPath, $url, $proxyUrl),
            'tokenFile' => $this->proxy?->tokenFile,
            'directory' => $this->proxy?->directory,
        ];

        if ($this->shellFailure) {
            throw new \RuntimeException('Turso shell exited with code 1');
        }
    }
}

final class DbShellTest extends Libsql3TestCase
{
    private function fakeMint(int &$calls): void
    {
        HttpTransport::fake(function () use (&$calls): array {
            $calls++;

            return [
                'status' => 201,
                'body' => [],
                'raw' => json_encode(['data' => ['token' => 'minted-secret']]),
            ];
        });
    }

    private function rawConfigSet(string $key, string $value): void
    {
        $pdo = new PDO('sqlite:' . config('db_path'));
        $table = config('tables.config');
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM $table WHERE key = :key");
        $stmt->execute(['key' => $key]);
        $sql = $stmt->fetchColumn() > 0
            ? "UPDATE $table SET value = :value WHERE key = :key"
            : "INSERT INTO $table (key, value) VALUES (:key, :value)";
        $pdo->prepare($sql)->execute(['key' => $key, 'value' => $value]);
    }

    public function test_db_shell_mints_201_and_launches_child_with_safe_display(): void
    {
        $this->seedLogin();
        config_set('LIBSQL_DATABASE_ENDPOINT', 'https://db.example.invalid:8080');
        $calls = 0;
        $this->fakeMint($calls);

        $command = new StubDbShell();
        $tester = new CommandTester($command);
        $exit = $tester->execute(['database_name' => 'demo']);

        $this->assertSame(0, $exit);
        $this->assertSame(1, $calls);
        $this->assertSame('/bin/sh', $command->captured['tursoPath']);
        $this->assertSame('https://demo.db.example.invalid:8080', $command->captured['url']);
        $this->assertSame('http://127.0.0.1:45999', $command->captured['proxyUrl']);
        $this->assertSame(
            "/bin/sh db shell 'https://demo.db.example.invalid:8080' --proxy 'http://127.0.0.1:45999'",
            $command->captured['command']
        );

        $display = $tester->getDisplay();
        $this->assertStringNotContainsString('minted-secret', $display);
        $this->assertStringNotContainsString('minted-secret', $command->captured['command']);
        $this->assertStringNotContainsString('process argument', $display);
        $this->assertStringNotContainsString('db:token:create demo', $display);
        $this->assertFileDoesNotExist($command->captured['tokenFile']);
        $this->assertDirectoryDoesNotExist($command->captured['directory']);
    }

    public function test_db_shell_child_command_never_carries_the_token_in_argv(): void
    {
        // Plan 009 Step 1 documented the exposed argv; the fix inverts it. The
        // exact command string is the assertion seam — display redaction alone
        // would never catch a regression here.
        $this->seedLogin();
        config_set('LIBSQL_DATABASE_ENDPOINT', 'https://db.example.invalid:8080');
        $calls = 0;
        $this->fakeMint($calls);

        $command = new StubDbShell();
        $tester = new CommandTester($command);
        $exit = $tester->execute(['database_name' => 'demo']);

        $this->assertSame(0, $exit);
        $this->assertSame(1, $calls);
        $this->assertSame(
            "/bin/sh db shell 'https://demo.db.example.invalid:8080' --proxy 'http://127.0.0.1:45999'",
            $command->captured['command']
        );
        $this->assertStringNotContainsString('authToken=', $command->captured['command']);
        $this->assertStringNotContainsString('minted-secret', $command->captured['command']);
    }

    public function test_db_shell_uses_scheme_default_port_when_endpoint_has_none(): void
    {
        $this->seedLogin();
        config_set('LIBSQL_DATABASE_ENDPOINT', 'http://127.0.0.1');
        $calls = 0;
        $this->fakeMint($calls);

        $command = new StubDbShell();
        $tester = new CommandTester($command);
        $exit = $tester->execute(['database_name' => 'demo']);

        $this->assertSame(0, $exit);
        $this->assertSame(1, $calls);
        $this->assertSame('http://demo.127.0.0.1:80', $command->captured['url']);
        $this->assertStringNotContainsString('minted-secret', $command->captured['command']);
    }

    public function test_db_shell_removes_credential_when_child_shell_fails(): void
    {
        $this->seedLogin();
        config_set('LIBSQL_DATABASE_ENDPOINT', 'https://db.example.invalid:8080');
        $calls = 0;
        $this->fakeMint($calls);

        $command = new StubDbShell();
        $command->shellFailure = true;
        $tester = new CommandTester($command);
        $exit = $tester->execute(['database_name' => 'demo']);

        $this->assertSame(1, $exit);
        $this->assertSame(1, $calls);
        $this->assertStringContainsString('Turso shell exited with code 1', $tester->getDisplay());
        $this->assertFileDoesNotExist($command->captured['tokenFile']);
        $this->assertDirectoryDoesNotExist($command->captured['directory']);
    }

    public function test_db_shell_does_not_mint_when_turso_is_missing_without_opt_in(): void
    {
        $this->seedLogin();
        config_set('LIBSQL_DATABASE_ENDPOINT', 'https://db.example.invalid');
        $calls = 0;
        $this->fakeMint($calls);

        $command = new StubDbShell();
        $command->tursoMissing = true;
        $tester = new CommandTester($command);
        $exit = $tester->execute(['database_name' => 'demo']);

        $this->assertSame(3, $exit);
        $this->assertSame(0, $calls, 'no credential may be minted before the executable is resolved');
        $display = $tester->getDisplay();
        $this->assertStringContainsString('curl -sSfL https://get.tur.so/install.sh | bash', $display);
        $this->assertStringContainsString('--install-turso', $display);
    }

    public function test_db_shell_rejects_unsafe_database_name_before_minting(): void
    {
        $this->seedLogin();
        $calls = 0;
        $this->fakeMint($calls);

        $tester = new CommandTester(new StubDbShell());
        $exit = $tester->execute(['database_name' => 'bad name; echo pwned']);

        $this->assertSame(1, $exit);
        $this->assertSame(0, $calls);
        $this->assertStringContainsString('Invalid database name', $tester->getDisplay());
    }

    public function test_db_shell_rejects_legacy_remote_http_endpoint_before_minting(): void
    {
        $this->seedLogin();
        $this->rawConfigSet('LIBSQL_DATABASE_ENDPOINT', 'http://192.0.2.10:8080');
        $calls = 0;
        $this->fakeMint($calls);

        $tester = new CommandTester(new StubDbShell());
        $exit = $tester->execute(['database_name' => 'demo']);

        $this->assertSame(1, $exit);
        $this->assertSame(0, $calls);
        $this->assertStringContainsString('plain HTTP is only allowed for loopback hosts', $tester->getDisplay());
    }
}
