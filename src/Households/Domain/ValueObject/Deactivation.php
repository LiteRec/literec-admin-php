<?php

declare(strict_types=1);

namespace App\Households\Domain\ValueObject;

use DateTimeImmutable;

/**
 * Records why and when a {@see App\Households\Domain\HouseholdMember} was
 * deactivated.
 *
 * Reason and timestamp are inseparable facts: a member is either active (no
 * Deactivation) or carries both together. Modelling them as one value object
 * keeps the pair from leaking onto the entity as two loosely-coupled nullable
 * getters. This is a projection of already-validated entity state, so it holds
 * the values without re-validating them.
 */
final readonly class Deactivation
{
    public function __construct(
        public string $reason,
        public DateTimeImmutable $at,
    ) {
    }

    public function equals(self $other): bool
    {
        return $this->reason === $other->reason
            && $this->at == $other->at;
    }
}
