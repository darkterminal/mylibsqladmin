<?php

namespace Libsql3\Internal;

use PDO;
use PDOException;

/**
 * The single source of truth for CLI identity and token precedence.
 *
 * Two rules matter here:
 *   1. The active user is an explicit marker, not a side effect of whichever
 *      cached token happens to expire last.
 *   2. Token precedence is: local store -> LIBSQL3_AUTH_TOKEN. The old generic
 *      TURSO_AUTH / AUTH_TOKEN / TOKEN names are deliberately gone: they could
 *      silently consume an unrelated tool's credential.
 */
final class AuthContext
{
    public const ACTIVE_USER_KEY = 'active_user';
    public const TOKEN_ENV = 'LIBSQL3_AUTH_TOKEN';

    private PDO $pdo;
    /** @var \Closure(string):void|null */
    private ?\Closure $warner;

    /**
     * @param \Closure(string):void|null $warner Injected by tests so the
     *        fallback warning can be asserted without capturing STDERR.
     */
    public function __construct(PDO $pdo, ?\Closure $warner = null)
    {
        $this->pdo = $pdo;
        $this->warner = $warner;
    }

    /**
     * The marked user wins while its token is still valid. Legacy stores that
     * predate the marker fall back to the newest still-valid token, so an
     * upgrade never logs an existing user out.
     */
    public function currentUser(): ?string
    {
        $marked = config_get(self::ACTIVE_USER_KEY);
        if ($marked !== null && $marked !== '' && $this->hasValidToken($marked)) {
            return $marked;
        }

        return $this->newestValidUser();
    }

    public function setActiveUser(string $username): void
    {
        config_set(self::ACTIVE_USER_KEY, $username);
    }

    public function clearActiveUser(): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM ' . config('tables.config') . ' WHERE key = :key'
        );
        $stmt->execute(['key' => self::ACTIVE_USER_KEY]);
    }

    public function hasValidToken(string $username): bool
    {
        return $this->validTokenRow($username) !== null;
    }

    /**
     * Stored token only, with expiry validation. Used by logout and rotation,
     * which must never act on an environment-provided credential.
     */
    public function storedToken(string $username): ?string
    {
        $row = $this->validTokenRow($username);

        return $row['token'] ?? null;
    }

    /**
     * Token precedence for API-calling commands. The namespaced environment
     * fallback is announced so a borrowed credential is never silent.
     */
    public function tokenFor(string $username): ?string
    {
        $token = $this->storedToken($username);
        if ($token !== null) {
            return $token;
        }

        $fromEnv = getenv(self::TOKEN_ENV);
        if (is_string($fromEnv) && $fromEnv !== '') {
            $this->warn(
                'Warning: using ' . self::TOKEN_ENV
                . ' environment variable for CLI authentication.'
            );

            return $fromEnv;
        }

        return null;
    }

    public function expiresAt(string $username): ?string
    {
        $row = $this->validTokenRow($username);

        return $row['expires_at'] ?? null;
    }

    public function deleteToken(string $username): void
    {
        $stmt = $this->pdo->prepare(
            'DELETE FROM ' . config('tables.tokens') . ' WHERE username = :username'
        );
        $stmt->execute(['username' => $username]);
    }

    private function validTokenRow(string $username): ?array
    {
        try {
            $stmt = $this->pdo->prepare("
                SELECT token, expires_at
                FROM " . config('tables.tokens') . "
                WHERE username = :username
                ORDER BY expires_at DESC
            ");
            $stmt->execute(['username' => $username]);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ($this->tokenIsValid($row['expires_at'])) {
                    return $row;
                }
            }
        } catch (PDOException) {
            // A broken store fails closed: no token, no identity.
        }

        return null;
    }

    private function newestValidUser(): ?string
    {
        try {
            $stmt = $this->pdo->query("
                SELECT username, expires_at
                FROM " . config('tables.tokens') . "
                ORDER BY expires_at DESC
            ");

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ($this->tokenIsValid($row['expires_at'])) {
                    return $row['username'];
                }
            }
        } catch (PDOException) {
            // Fail closed, same as validTokenRow().
        }

        return null;
    }

    /**
     * The one expiry predicate for the CLI. An ISO-8601 value with an explicit
     * offset parses correctly under any date.timezone; malformed input fails
     * closed rather than silently extending a session.
     */
    private function tokenIsValid(mixed $expiresAt): bool
    {
        if (!is_string($expiresAt) || trim($expiresAt) === '') {
            return false;
        }
        $timestamp = strtotime($expiresAt);

        return $timestamp !== false && $timestamp >= time();
    }

    private function warn(string $message): void
    {
        if ($this->warner !== null) {
            ($this->warner)($message);

            return;
        }
        if (defined('STDERR')) {
            fwrite(STDERR, $message . PHP_EOL);
        } else {
            error_log($message);
        }
    }
}
