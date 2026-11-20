<?php

namespace Libsql3\Internal;

use PDO;
use PDOException;

trait CliStore
{
    private ?PDO $pdo = null;
    private bool $dbInitialized = false;

    protected function authCheck(string $username): bool
    {
        try {
            $stmt = $this->getPdo()->prepare("
                SELECT token, expires_at
                FROM " . config('tables.tokens') . "
                WHERE username = :username
                ORDER BY expires_at DESC
            ");
            $stmt->execute(['username' => $username]);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ($this->tokenIsValid($row['expires_at'])) {
                    return true;
                }
            }
            return false;
        } catch (PDOException $e) {
            // Handle database errors appropriately
            return false;
        }
    }

    /**
     * The single expiry predicate for the whole CLI. ISO-8601 strings with an
     * explicit offset are parsed correctly regardless of date.timezone; a
     * malformed or missing value fails closed.
     */
    private function tokenIsValid(mixed $expiresAt): bool
    {
        if (!is_string($expiresAt) || trim($expiresAt) === '') {
            return false;
        }
        $timestamp = strtotime($expiresAt);

        return $timestamp !== false && $timestamp >= time();
    }

    protected function getCurrentLoggedInUser(): ?string
    {
        try {
            $stmt = $this->getPdo()->query("
                SELECT username, expires_at
                FROM " . config('tables.tokens') . "
                ORDER BY expires_at DESC
            ");

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ($this->tokenIsValid($row['expires_at'])) {
                    return $row['username'];
                }
            }
            return null;
        } catch (PDOException $e) {
            // Handle potential database errors
            return null;
        }
    }

    protected function storeToken(string $username, string $token, string $expiresAt): void
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
    }

    protected function getToken(string $username): ?string
    {
        try {
            $stmt = $this->getPdo()->prepare("
                SELECT token, expires_at
                FROM " . config('tables.tokens') . "
                WHERE username = :username
                ORDER BY expires_at DESC
            ");
            $stmt->execute(['username' => $username]);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ($this->tokenIsValid($row['expires_at'])) {
                    return $row['token'];
                }
            }
            return null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function getExpiresAt(string $username): ?string
    {
        try {
            $stmt = $this->getPdo()->prepare("
                SELECT expires_at
                FROM " . config('tables.tokens') . "
                WHERE username = :username
                ORDER BY expires_at DESC
            ");
            $stmt->execute(['username' => $username]);

            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if ($this->tokenIsValid($row['expires_at'])) {
                    return $row['expires_at'];
                }
            }
            return null;
        } catch (PDOException $e) {
            return null;
        }
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
