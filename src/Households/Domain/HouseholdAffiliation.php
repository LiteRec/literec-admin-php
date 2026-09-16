<?php

declare(strict_types=1);

namespace App\Households\Domain;

use App\Households\Domain\ValueObject\HouseholdId;
use DateTimeImmutable;

/**
 * Child entity owned by {@see HouseholdMember}, recording that the member's
 * home household has shared them with another household (LRA-210 — e.g. a
 * minor in shared custody). Identity is the derived composite
 * (member, householdId) pair; there is no standalone id column.
 *
 * Although the constructor and accessors are technically `public` (PHP has
 * no package-private modifier), this is considered internal to the
 * aggregate: callers in Application or Infrastructure layers must go
 * through {@see Household} methods. Direct instantiation outside the
 * aggregate is a programming error.
 */
final class HouseholdAffiliation
{
    private HouseholdMember $member;
    private HouseholdId $householdId;
    private DateTimeImmutable $linkedAt;

    /**
     * Internal-to-aggregate constructor. Use
     * {@see MemberInHousehold::shareWithHousehold()} to create instances.
     */
    public function __construct(HouseholdMember $member, HouseholdId $householdId, DateTimeImmutable $linkedAt)
    {
        $this->member = $member;
        $this->householdId = $householdId;
        $this->linkedAt = $linkedAt;
    }

    public function householdId(): HouseholdId
    {
        return $this->householdId;
    }

    public function linkedAt(): DateTimeImmutable
    {
        return $this->linkedAt;
    }
}
