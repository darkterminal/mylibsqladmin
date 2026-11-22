<?php

declare(strict_types=1);

namespace Libsql3\Tests;

use Libsql3\Internal\ShellTokenProxy;
use PHPUnit\Framework\TestCase;

final class ShellTokenProxyTest extends TestCase
{
    public function test_credential_file_is_private_and_removed(): void
    {
        [$directory, $tokenFile] = ShellTokenProxy::writeTokenFile('minted-secret');

        try {
            $this->assertSame(0700, fileperms($directory) & 0777);
            $this->assertSame(0600, fileperms($tokenFile) & 0777);
            $this->assertSame('minted-secret', file_get_contents($tokenFile));
        } finally {
            ShellTokenProxy::removeTokenFile($tokenFile, $directory);
        }

        $this->assertFileDoesNotExist($tokenFile);
        $this->assertDirectoryDoesNotExist($directory);
    }

    public function test_cleanup_is_idempotent_after_the_child_already_removed_the_file(): void
    {
        [$directory, $tokenFile] = ShellTokenProxy::writeTokenFile('minted-secret');

        // The CLI entrypoint installs a handler that prints even @-suppressed
        // warnings. Mimic it so a stale unlink/rmdir would fail this test.
        $warnings = [];
        set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = $errstr;
            return true;
        });

        try {
            ShellTokenProxy::removeTokenFile($tokenFile, $directory);
            ShellTokenProxy::removeTokenFile($tokenFile, $directory);
        } finally {
            restore_error_handler();
        }

        $this->assertSame([], $warnings);
        $this->assertFileDoesNotExist($tokenFile);
        $this->assertDirectoryDoesNotExist($directory);
    }

    public function test_forwarded_request_injects_only_the_file_token(): void
    {
        $request = "POST /v2/pipeline HTTP/1.1\r\n"
            . "Host: demo.db.example.invalid:8080\r\n"
            . "Authorization: Bearer stale-client-value\r\n"
            . "Content-Length: 12\r\n"
            . "Accept-Encoding: gzip\r\n"
            . "\r\n"
            . '{"sql":"go"}';

        $forwarded = ShellTokenProxy::buildForwardedRequest($request, 'minted-secret');

        $this->assertStringStartsWith("POST /v2/pipeline HTTP/1.1\r\n", $forwarded);
        $this->assertStringContainsString("Host: demo.db.example.invalid:8080\r\n", $forwarded);
        $this->assertStringContainsString("Authorization: Bearer minted-secret\r\n", $forwarded);
        $this->assertStringNotContainsString('stale-client-value', $forwarded);
        $this->assertStringContainsString("Accept-Encoding: gzip\r\n", $forwarded);
        $this->assertStringEndsWith("\r\n\r\n" . '{"sql":"go"}', $forwarded);
        $this->assertSame(1, substr_count($forwarded, 'Authorization:'));
    }

    public function test_forwarded_request_rejects_header_injection_in_token(): void
    {
        $request = "POST /v2/pipeline HTTP/1.1\r\nHost: demo.db.example.invalid\r\n\r\n";

        $this->expectException(\RuntimeException::class);
        ShellTokenProxy::buildForwardedRequest($request, "secret\r\nX-Evil: 1");
    }

    public function test_read_request_consumes_header_and_content_length_body(): void
    {
        [$writer, $reader] = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, 0);
        $this->assertNotFalse($writer);
        $this->assertNotFalse($reader);

        $request = "POST /v2/pipeline HTTP/1.1\r\n"
            . "Host: demo.db.example.invalid:8080\r\n"
            . "Content-Length: 5\r\n"
            . "\r\n"
            . 'hello';

        try {
            fwrite($writer, substr($request, 0, 20));
            fwrite($writer, substr($request, 20, 15));
            fwrite($writer, substr($request, 35));
            $this->assertSame($request, ShellTokenProxy::readRequest($reader));
        } finally {
            fclose($writer);
            fclose($reader);
        }
    }
}
