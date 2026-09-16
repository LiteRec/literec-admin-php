<?php

declare(strict_types=1);

namespace App\Households\Domain\ValueObject;

use DateTimeImmutable;

/**
 * Read projection of a single {@see \App\Households\Domain\HouseholdAffiliation}
 * (LRA-237): which household a minor member has been shared with (LRA-210)
 * and when the share was created.
 *
 * Element type of {@see MemberHouseholdLinks}. This is a read of
 * already-validated entity state, not re-validation, the same pattern
 * {@see Deactivation} and {@see MemberMerge} use.
 */
final readonly class HouseholdLink
{
    private function __construct(
        public HouseholdId $householdId,
        public DateTimeImmutable $linkedAt,
    ) {
    }

    public static function of(HouseholdId $householdId, DateTimeImmutable $linkedAt): self
    {
        return new self($householdId, $linkedAt);
    }

    public function equals(self $other): bool
    {
        return $this->householdId->equals($other->householdId)
            && $this->linkedAt == $other->linkedAt;
    }
}
