<?php

declare(strict_types=1);

namespace Libsql3\Internal;

/**
 * Loopback HTTP injector that keeps a minted database token out of the Turso
 * child's argv.
 *
 * turso v1.0.31 accepts a database token only as an `authToken` query
 * parameter, which is visible to every local user in the process list. Its
 * `--proxy` flag, however, routes the whole HRANA `POST /v2/pipeline` exchange
 * through an HTTP proxy while preserving the original Host header. This class
 * binds a loopback listener, forks a small child that adds the Authorization
 * header from a private token file, and gives the parent a token-free proxy URL
 * to pass to Turso.
 *
 * The token is never placed in argv, an environment variable, a log line, or
 * an exception message. It exists only in a 0600 file inside a 0700 directory,
 * which is removed on every exit path.
 */
final class ShellTokenProxy
{
    private const MAX_HEADER_BYTES = 65536;
    private const MAX_BODY_BYTES = 1048576;
    private const CONNECT_TIMEOUT = 10;
    private const IO_TIMEOUT = 15;

    public function __construct(
        public readonly int $port,
        public readonly string $tokenFile,
        public readonly string $directory,
        public readonly int $pid,
    ) {
    }

    public function proxyUrl(): string
    {
        return 'http://127.0.0.1:' . $this->port;
    }

    /**
     * Create the private credential file and start the injector child.
     *
     * On any failure before the fork, the credential file is removed. The
     * caller owns the returned proxy and must call stop() in a finally block.
     */
    public static function start(string $token, string $upstreamUrl): self
    {
        [$directory, $tokenFile] = self::writeTokenFile($token);

        $server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($server === false) {
            self::removeTokenFile($tokenFile, $directory);
            throw new \RuntimeException('Failed to open loopback injector socket: ' . (string) $errstr);
        }

        $socketName = (string) stream_socket_get_name($server, false);
        $separator = strrpos($socketName, ':');
        $port = $separator === false ? 0 : (int) substr($socketName, $separator + 1);
        if ($port <= 0) {
            fclose($server);
            self::removeTokenFile($tokenFile, $directory);
            throw new \RuntimeException('Failed to resolve loopback injector port');
        }

        $parentPid = getmypid();
        $pid = pcntl_fork();
        if ($pid === -1) {
            fclose($server);
            self::removeTokenFile($tokenFile, $directory);
            throw new \RuntimeException('Failed to start loopback injector');
        }

        if ($pid === 0) {
            // The injector must never write to the operator's terminal. The CLI
            // entrypoint installs handlers that print diagnostics to STDERR.
            restore_error_handler();
            restore_exception_handler();
            error_reporting(0);
            try {
                self::serve($server, $tokenFile, $upstreamUrl, $parentPid);
            } catch (\Throwable $e) {
                // Deliberately silent: an exception message could carry the token.
            }
            exit(0);
        }

        // The child inherited the listener; the parent no longer needs it.
        fclose($server);

        return new self($port, $tokenFile, $directory, $pid);
    }

    /** Terminate the injector child and remove its credential file. */
    public function stop(): void
    {
        // pid <= 0 is a test-injected handle with no child to terminate.
        if ($this->pid <= 0) {
            self::removeTokenFile($this->tokenFile, $this->directory);
            return;
        }

        @posix_kill($this->pid, SIGTERM);

        $reaped = false;
        $deadline = microtime(true) + 2.0;
        while (microtime(true) < $deadline) {
            $waited = pcntl_waitpid($this->pid, $status, WNOHANG);
            if ($waited === $this->pid || $waited === -1) {
                $reaped = true;
                break;
            }
            usleep(20000);
        }

        if (!$reaped) {
            @posix_kill($this->pid, SIGKILL);
            pcntl_waitpid($this->pid, $status);
        }

        self::removeTokenFile($this->tokenFile, $this->directory);
    }

    /**
     * @return array{0:string, 1:string} directory then token file path
     */
    public static function writeTokenFile(string $token): array
    {
        $directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR
            . 'libsql3-shell-' . bin2hex(random_bytes(8));
        $tokenFile = $directory . DIRECTORY_SEPARATOR . 'token';

        $oldUmask = umask(0077);
        try {
            if (!mkdir($directory, 0700)) {
                throw new \RuntimeException('Failed to create private injector directory');
            }
            $handle = @fopen($tokenFile, 'xb');
            if ($handle === false) {
                @rmdir($directory);
                throw new \RuntimeException('Failed to create injector credential file');
            }
            try {
                fwrite($handle, $token);
            } finally {
                fclose($handle);
            }
            chmod($tokenFile, 0600);
        } finally {
            umask($oldUmask);
        }

        return [$directory, $tokenFile];
    }

    /**
     * Idempotent by design: the injector child removes the same paths from its
     * own shutdown/signal handler before the parent's finally block runs. The
     * CLI error handler prints even suppressed warnings, so touching a path
     * that is already gone would surface a stale "No such file" diagnostic
     * after an otherwise successful shell session (F-53).
     */
    public static function removeTokenFile(string $tokenFile, string $directory): void
    {
        if (is_file($tokenFile)) {
            @unlink($tokenFile);
        }
        if (is_dir($directory)) {
            @rmdir($directory);
        }
    }

    /**
     * Injector child loop. Runs until the parent exits or is killed.
     *
     * @param resource $server listening loopback socket inherited from the parent
     */
    public static function serve($server, string $tokenFile, string $upstreamUrl, int $parentPid): void
    {
        $token = trim((string) file_get_contents($tokenFile));
        $cleanup = static function () use ($tokenFile): void {
            self::removeTokenFile($tokenFile, dirname($tokenFile));
        };

        register_shutdown_function($cleanup);

        // Ctrl-C is delivered to the whole process group, so the parent's
        // finally block may never run. The child must clean up after itself.
        pcntl_async_signals(true);
        foreach ([SIGTERM, SIGINT, SIGHUP] as $signal) {
            pcntl_signal($signal, static function () use ($cleanup): void {
                $cleanup();
                exit(0);
            });
        }

        while (posix_getppid() === $parentPid) {
            $client = @stream_socket_accept($server, 0.5);
            if ($client === false) {
                continue;
            }
            stream_set_timeout($client, self::IO_TIMEOUT);
            try {
                $request = self::readRequest($client);
                if ($request !== null && $request !== '') {
                    self::relay($client, $request, $upstreamUrl, $token);
                }
            } catch (\Throwable $e) {
                // Never echo $e: its message could contain the credential.
                @fwrite($client, "HTTP/1.1 502 Bad Gateway\r\nContent-Length: 0\r\nConnection: close\r\n\r\n");
            } finally {
                fclose($client);
            }
        }
    }

    /**
     * Read one complete HTTP/1.1 request, including its Content-Length body.
     *
     * @param resource $client
     */
    public static function readRequest($client): ?string
    {
        $raw = '';
        while (!str_contains($raw, "\r\n\r\n")) {
            $chunk = fread($client, 8192);
            if ($chunk === false || $chunk === '') {
                return $raw === '' ? null : $raw;
            }
            $raw .= $chunk;
            if (strlen($raw) > self::MAX_HEADER_BYTES) {
                throw new \RuntimeException('Injector request header too large');
            }
        }

        $headerEnd = strpos($raw, "\r\n\r\n");
        $header = substr($raw, 0, $headerEnd);
        $body = substr($raw, $headerEnd + 4);

        $contentLength = 0;
        foreach (explode("\r\n", $header) as $line) {
            if (preg_match('/^Content-Length:\s*(\d+)/i', $line, $matches) === 1) {
                $contentLength = (int) $matches[1];
            }
        }
        if ($contentLength > self::MAX_BODY_BYTES) {
            throw new \RuntimeException('Injector request body too large');
        }

        while (strlen($body) < $contentLength) {
            $chunk = fread($client, 8192);
            if ($chunk === false || $chunk === '') {
                break;
            }
            $body .= $chunk;
        }

        return $header . "\r\n\r\n" . $body;
    }

    /**
     * Pure request rewriting, kept separate from I/O so the header policy can be
     * asserted without a socket pair. Inbound Authorization is always discarded:
     * the only credential sent upstream comes from the private file.
     */
    public static function buildForwardedRequest(string $request, string $token): string
    {
        $headerEnd = strpos($request, "\r\n\r\n");
        if ($headerEnd === false) {
            throw new \RuntimeException('Malformed injector request');
        }
        $header = substr($request, 0, $headerEnd);
        $body = substr($request, $headerEnd + 4);
        $lines = explode("\r\n", $header);
        $requestLine = array_shift($lines);

        if (!is_string($requestLine)
            || preg_match('#^[A-Z]+ /[^ ]* HTTP/1\.[01]$#', $requestLine) !== 1
        ) {
            throw new \RuntimeException('Malformed injector request line');
        }
        if (str_contains($token, "\r") || str_contains($token, "\n")) {
            throw new \RuntimeException('Invalid injector credential');
        }

        $forwarded = [$requestLine];
        foreach ($lines as $line) {
            if (preg_match('/^(Connection|Authorization|Proxy-Authorization|Content-Length|Transfer-Encoding|Keep-Alive|TE|Trailer|Upgrade):/i', $line) === 1) {
                continue;
            }
            $forwarded[] = $line;
        }
        $forwarded[] = 'Authorization: Bearer ' . $token;
        $forwarded[] = 'Content-Length: ' . strlen($body);
        $forwarded[] = 'Connection: close';

        return implode("\r\n", $forwarded) . "\r\n\r\n" . $body;
    }

    /**
     * Forward one request to the real database endpoint with the Authorization
     * header attached. Response bytes are relayed verbatim; nothing is logged.
     *
     * @param resource $client
     */
    public static function relay($client, string $request, string $upstreamUrl, string $token): void
    {
        $headerEnd = strpos($request, "\r\n\r\n");
        if ($headerEnd === false) {
            throw new \RuntimeException('Malformed injector request');
        }
        $header = substr($request, 0, $headerEnd);
        $requestLine = explode("\r\n", $header, 2)[0];

        if (preg_match('#^([A-Z]+) (/[^ ]*) HTTP/1\.[01]$#', $requestLine) !== 1) {
            throw new \RuntimeException('Malformed injector request line');
        }

        $upstream = parse_url($upstreamUrl);
        $scheme = $upstream['scheme'] ?? '';
        $host = $upstream['host'] ?? '';
        if (!in_array($scheme, ['http', 'https'], true) || $host === '') {
            throw new \RuntimeException('Malformed injector upstream URL');
        }
        $port = $upstream['port'] ?? ($scheme === 'https' ? 443 : 80);
        $remote = ($scheme === 'https' ? 'ssl://' : 'tcp://') . $host . ':' . $port;

        $forwarded = self::buildForwardedRequest($request, $token);

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'SNI_enabled' => true,
                'peer_name' => $host,
            ],
        ]);
        $upstreamSocket = @stream_socket_client(
            $remote,
            $errno,
            $errstr,
            self::CONNECT_TIMEOUT,
            STREAM_CLIENT_CONNECT,
            $context
        );
        if ($upstreamSocket === false) {
            throw new \RuntimeException('Failed to reach upstream database endpoint');
        }

        try {
            stream_set_timeout($upstreamSocket, self::IO_TIMEOUT);
            fwrite($upstreamSocket, $forwarded);
            while (!feof($upstreamSocket)) {
                $chunk = fread($upstreamSocket, 8192);
                if ($chunk === false || $chunk === '') {
                    if (stream_get_meta_data($upstreamSocket)['timed_out'] === true) {
                        break;
                    }
                    continue;
                }
                fwrite($client, $chunk);
            }
        } finally {
            fclose($upstreamSocket);
        }
    }
}
