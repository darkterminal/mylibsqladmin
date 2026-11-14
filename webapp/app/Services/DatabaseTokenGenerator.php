<?php

namespace App\Services;

/**
 * Opaque bearer tokens for the sqld data plane.
 *
 * No JWT, no third-party signing keys, no filesystem side effects. Tokens are
 * random 64-char hex strings; their SHA-256 hash is what gets stored in the
 * database, so a leaked DB backup does not disclose usable credentials.
 * Authors only ever see the plaintext at creation time.
 */
class DatabaseTokenGenerator
{
    /**
     * Kept for call-site compatibility; no clock dependence on the client side.
     */
    public function setTokenExpiration(int $expiration): void
    {
        // Pinned in the caller via the expiration column instead.
    }

    /**
     * Generate a full-access / read-only token pair.
     *
     * @param  int|string  $databaseId   Database id or name (opaque to the token itself).
     * @param  int|null    $userOrGroupId  Owner id (opaque to the token itself).
     * @param  int         $tokenExpiration  Days the caller will advertise; 0 means never.
     * @param  bool        $isGroup      Unused, kept for call-site compatibility.
     * @return array{full_access_token: string, read_only_token: string}
     */
    public function generateToken(
        int|string $databaseId,
        int|null $userOrGroupId = null,
        int $tokenExpiration = 0,
        bool $isGroup = false,
    ): array {
        return [
            'full_access_token' => bin2hex(random_bytes(32)),
            'read_only_token' => bin2hex(random_bytes(32)),
        ];
    }
}
