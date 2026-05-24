<?php

declare(strict_types=1);

namespace App\Households\Application\Query\Port;

/**
 * Composite read-model DTO for the member detail page (LRA-41 + LRA-42):
 * everything needed to render the page in a single payload, with no
 * domain types leaking through.
 *
 * `householdMembers` carries every member of the household (including the
 * active member itself), sorted by lastName / firstName / id, so the
 * Household card (LRA-42) can render a clickable roster without issuing a
 * second query.
 */
final readonly class MemberDetail
{
    /**
     * @param list<MemberListItem> $householdMembers
     */
    public function __construct(
        public HouseholdSummary $household,
        public MemberProfileDto $profile,
        public MemberAddressDto $address,
        public MemberResidencyDto $residency,
        public array $householdMembers,
    ) {
    }
}
