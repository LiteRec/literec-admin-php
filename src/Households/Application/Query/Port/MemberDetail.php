<?php

declare(strict_types=1);

namespace App\Households\Application\Query\Port;

/**
 * Composite read-model DTO for the member detail page (LRA-41 + LRA-42):
 * everything needed to render the page in a single payload, with no
 * domain types leaking through.
 */
final readonly class MemberDetail
{
    public function __construct(
        public HouseholdSummary $household,
        public MemberProfileDto $profile,
        public MemberAddressDto $address,
        public MemberResidencyDto $residency,
    ) {
    }
}
