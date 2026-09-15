<?php

declare(strict_types=1);

namespace App\Households\Domain\ValueObject;

use DateTimeImmutable;

/**
 * Records which survivor a {@see App\Households\Domain\HouseholdMember}
 * was merged into, and when.
 *
 * Mirrors {@see Deactivation}: the survivor id and timestamp are
 * inseparable facts about a merged member, so they are modelled together
 * rather than as two loosely-coupled nullable getters. This is a
 * projection of already-validated entity state, so it holds the values
 * without re-validating them.
 */
final readonly class MemberMerge
{
    public function __construct(
        public MemberId $intoMemberId,
        public DateTimeImmutable $at,
    ) {
    }

    public function equals(self $other): bool
    {
        return $this->intoMemberId->equals($other->intoMemberId)
            && $this->at == $other->at;
    }
}
