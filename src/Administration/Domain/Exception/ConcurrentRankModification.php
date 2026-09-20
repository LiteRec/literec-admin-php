<?php

declare(strict_types=1);

namespace App\Administration\Domain\Exception;

use RuntimeException;
use Throwable;

/**
 * Raised when a Rank save races a concurrent modification. The Doctrine
 * optimistic lock surfaces as {@see \Doctrine\ORM\OptimisticLockException};
 * the Doctrine repository adapter wraps it as this named exception so
 * callers can react to the race without depending on the ORM directly.
 */
final class ConcurrentRankModification extends RuntimeException implements AdministrationDomainException
{
    public static function for(string $id, ?Throwable $previous = null): self
    {
        return new self(
            sprintf('Rank %s was modified concurrently — reload and retry.', $id),
            0,
            $previous,
        );
    }
}
