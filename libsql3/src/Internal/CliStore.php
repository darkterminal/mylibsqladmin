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
                FROM " . $this->config['token_table'] . " 
                WHERE username = :username 
                ORDER BY expires_at DESC 
                LIMIT 1
            ");
            $stmt->execute(['username' => $username]);

            if ($tokenData = $stmt->fetch(PDO::FETCH_ASSOC)) {
                return strtotime($tokenData['expires_at']) >= time();
            }
            return false;
        } catch (PDOException $e) {
            // Handle database errors appropriately
            return false;
        }
    }

    protected function getCurrentLoggedInUser(): ?string
    {
        try {
            $stmt = $this->getPdo()->prepare("
            SELECT username 
            FROM " . $this->config['token_table'] . " 
            WHERE expires_at > datetime('now', 'localtime')
            ORDER BY expires_at DESC 
            LIMIT 1
        ");
            $stmt->execute();
            return $stmt->fetchColumn() ?: null;
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
                DELETE FROM " . $this->config['token_table'] . " 
                WHERE username = :username
            ");
            $stmt->execute(['username' => $username]);

            // Insert new token
            $stmt = $pdo->prepare("
                INSERT INTO " . $this->config['token_table'] . " 
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
                SELECT token 
                FROM " . $this->config['token_table'] . " 
                WHERE username = :username 
                  AND expires_at > datetime('now', 'localtime')
                ORDER BY expires_at DESC 
                LIMIT 1
            ");
            $stmt->execute(['username' => $username]);
            return $stmt->fetchColumn() ?: null;
        } catch (PDOException $e) {
            return null;
        }
    }

    public function getExpiresAt(string $username): ?string
    {
        try {
            $stmt = $this->getPdo()->prepare("
                SELECT expires_at 
                FROM " . $this->config['token_table'] . " 
                WHERE username = :username 
                  AND expires_at > datetime('now', 'localtime')
                ORDER BY expires_at DESC 
                LIMIT 1
            ");
            $stmt->execute(['username' => $username]);
            return $stmt->fetchColumn() ?: null;
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
        // Create storage directory if needed
        $dbDir = dirname($this->config['db_path']);
        if (!is_dir($dbDir)) {
            mkdir($dbDir, 0700, true);
        }

        try {
            $this->pdo = new PDO('sqlite:' . $this->config['db_path']);
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            // Create table if not exists
            $this->pdo->exec("
                CREATE TABLE IF NOT EXISTS " . $this->config['token_table'] . " (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    username TEXT NOT NULL,
                    token TEXT NOT NULL,
                    expires_at DATETIME NOT NULL,
                    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
                )
            ");

            // Create index for faster lookups
            $this->pdo->exec("
                CREATE INDEX IF NOT EXISTS idx_username 
                ON " . $this->config['token_table'] . " (username)
            ");

            $this->dbInitialized = true;
        } catch (PDOException $e) {
            // Handle initialization errors
            throw new \RuntimeException("Database initialization failed: " . $e->getMessage());
        }
    }
}
