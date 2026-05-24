<?php

declare(strict_types=1);

namespace App\Households\Application\Query\Port;

/**
 * Compact household projection used by the Household card on the member
 * detail page (LRA-42). Carries just enough to render the card without
 * needing a second round-trip for household data.
 */
final readonly class HouseholdSummary
{
    public function __construct(
        public string $householdId,
        public string $householdName,
        public int $memberCount,
        public string $primaryMemberId,
        public string $primaryMemberFullName,
    ) {
    }
}
