<?php

declare(strict_types=1);

namespace App\Households\Application\Query\Port;

/**
 * Composite read-model DTO for the member detail page (LRA-41 + LRA-42):
 * everything needed to render the page in a single payload, with no
 * domain types leaking through.
 *
 * `household`, `address`, and `householdMembers` all reflect the household
 * being VIEWED (the householdId the query was made against), which may
 * differ from the member's home household when the member is a shared
 * minor (LRA-210) viewed from a linked household. `homeHouseholdId` names
 * the household that owns the member's identity data — the Profile and
 * Residency (and Contact) cards build their edit URLs from it so mutations
 * always land on the aggregate that actually owns the member row.
 *
 * `householdMembers` carries every member of the viewed household
 * (including the active member itself) plus every member shared into it,
 * sorted by lastName / firstName / id, so the Household card (LRA-42) can
 * render a clickable roster without issuing a second query.
 */
final readonly class MemberDetail
{
    /**
     * @param list<MemberListItem>     $householdMembers
     * @param list<LinkedHouseholdDto> $linkedHouseholds Home entry first, then shared households in link order.
     */
    public function __construct(
        public HouseholdSummary $household,
        public MemberProfileDto $profile,
        public MemberAddressDto $address,
        public MemberResidencyDto $residency,
        public array $householdMembers,
        public string $homeHouseholdId,
        public array $linkedHouseholds,
    ) {
    }
}
