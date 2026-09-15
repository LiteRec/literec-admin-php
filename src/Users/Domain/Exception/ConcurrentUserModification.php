<?php

declare(strict_types=1);

namespace App\Users\Domain\Exception;

use RuntimeException;
use Throwable;

/**
 * Raised when a User aggregate save races a concurrent modification — most
 * notably two logins racing to consume the same one-time password (LRA-213).
 * The Doctrine optimistic lock surfaces as
 * {@see \Doctrine\ORM\OptimisticLockException}; the Doctrine repository
 * adapter wraps it as this named exception so callers can react to the race
 * without depending on the ORM directly.
 */
final class ConcurrentUserModification extends RuntimeException implements UsersDomainException
{
    public static function forUser(string $id, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('User %s was modified concurrently — reload and retry.', $id),
            0,
            $previous,
        );
    }
}
