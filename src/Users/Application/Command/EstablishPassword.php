<?php

declare(strict_types=1);

namespace App\Users\Application\Command;

/**
 * User-initiated command that sets a new password and returns the account
 * to the Established credential state, closing out a one-time password
 * (or serving a future voluntary password change).
 */
final readonly class EstablishPassword
{
    public function __construct(
        public string $userId,
        public string $plaintextPassword,
    ) {
    }
}
