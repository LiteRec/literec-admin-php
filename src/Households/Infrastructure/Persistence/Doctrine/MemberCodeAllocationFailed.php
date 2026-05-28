<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Persistence\Doctrine;

use RuntimeException;

/**
 * Raised by {@see DoctrineMemberCodeAllocator} when the backing Postgres
 * sequence cannot yield the next member-code value. This is an
 * infrastructure failure (the sequence is missing, the connection
 * dropped, or the driver returned an unusable value), not a domain
 * invariant breach, so it deliberately does NOT implement
 * HouseholdsDomainException — it should surface as a 5xx, not be mapped
 * to a client-facing validation error.
 */
final class MemberCodeAllocationFailed extends RuntimeException
{
    public static function sequenceReturnedNoValue(string $sequenceName): self
    {
        return new self(sprintf(
            'Failed to allocate the next value from sequence "%s".',
            $sequenceName,
        ));
    }
}
