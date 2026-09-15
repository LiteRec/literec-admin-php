<?php

declare(strict_types=1);

namespace App\Users\Application\Command;

/**
 * Dispatched by {@see \App\Users\Infrastructure\Security\ConsumeOneTimePasswordOnLogin}
 * the moment a user authenticates with an issued one-time password, marking
 * the credential as used before the request is allowed to proceed.
 */
final readonly class ConsumeOneTimePassword
{
    public function __construct(
        public string $userId,
    ) {
    }
}
