<?php

namespace Libsql3\Internal;

use PDO;
use PDOException;

/**
 * Thin adapter between command classes and AuthContext.
 *
 * Commands keep calling the same helper names; the identity/token rules now
 * live in exactly one place so no command can reimplement (or drift in) token
 * precedence again.
 */
trait CliStore
{
    private ?PDO $pdo = null;
    private bool $dbInitialized = false;

    protected function authCheck(string $username): bool
    {
        return $this->authContext()->hasValidToken($username);
    }

    protected function getCurrentLoggedInUser(): ?string
    {
        return $this->authContext()->currentUser();
    }

    protected function storeToken(string $username, string $token, string $expiresAt, ?string $timezone = null): void
    {
        $pdo = $this->getPdo();

        $pdo->beginTransaction();
        try {
            // Delete expired tokens for this user
            $stmt = $pdo->prepare("
                DELETE FROM " . config('tables.tokens') . "
                WHERE username = :username
            ");
            $stmt->execute(['username' => $username]);

            // Insert new token
            $stmt = $pdo->prepare("
                INSERT INTO " . config('tables.tokens') . "
                (username, token, expires_at)
                VALUES (:username, :token, :expires_at)
            ");

            $stmt->execute([
                'username' => $username,
                'token' => $token,
                'expires_at' => $expiresAt
            ]);

            $pdo->commit();
        } catch (PDOException $e) {
            $pdo->rollBack();
            throw $e;
        }

        // A successful token write defines the active identity. Commands must
        // never fall back to a global "latest expiry" guess after this point.
        $this->authContext()->setActiveUser($username);

        // Display-only metadata from the server. An invalid value is ignored so
        // a hostile/old endpoint cannot poison date rendering (F-51).
        if (is_valid_display_timezone($timezone)) {
            config_set('LIBSQL3_DISPLAY_TIMEZONE', $timezone);
        }
    }

    /**
     * Stored, expiry-validated token only. Logout and rotation use this so an
     * environment credential can never be revoked or overwritten by mistake.
     */
    protected function getToken(string $username): ?string
    {
        return $this->authContext()->storedToken($username);
    }

    /**
     * Token resolution for API-calling commands: local store first, then the
     * single namespaced environment fallback (which emits a visible warning).
     */
    protected function getTokenForUser(string $userIdentifier): ?string
    {
        return $this->authContext()->tokenFor($userIdentifier);
    }

    public function getExpiresAt(string $username): ?string
    {
        return $this->authContext()->expiresAt($username);
    }

    protected function deleteToken(string $username): void
    {
        $this->authContext()->deleteToken($username);
    }

    protected function clearActiveUser(): void
    {
        $this->authContext()->clearActiveUser();
    }

    private function authContext(): AuthContext
    {
        return new AuthContext($this->getPdo());
    }

    private function getPdo(): PDO
    {
        if ($this->pdo === null) {
            $this->initializeDatabase();
        }
        return $this->pdo;
    }

    private function initializeDatabase(): void
    {
        // Schema creation lives in initialize_local_store() so bootstrap code
        // can prepare it before any command instance is constructed.
        $this->pdo = initialize_local_store();
        $this->dbInitialized = true;
    }
}
