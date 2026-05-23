<?php

declare(strict_types=1);

namespace App\Users\Application\Command;

/**
 * System command for upgrading a user's stored password hash to a newer
 * algorithm. Dispatched by SecurityUserProvider::upgradePassword() when
 * Symfony Security detects that the hash needs rotating; the
 * newHashedPassword field carries the already-hashed value Symfony
 * produced, so the handler does NOT hash again.
 *
 * For user-initiated password changes, introduce a separate command
 * that takes a plaintext password and hashes inside the handler.
 */
final readonly class ChangeUserPassword
{
    public function __construct(
        public string $userId,
        public string $newHashedPassword,
    ) {
    }
}
