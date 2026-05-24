<?php

declare(strict_types=1);

namespace App\Households\Application\Query;

/**
 * Query bus message for the member detail use case. Carries primitive
 * string identifiers; the handler builds the {@see HouseholdId} and
 * {@see MemberId} value objects so invalid input surfaces as a domain
 * exception at the bus boundary rather than a constructor TypeError.
 */
final readonly class GetMemberDetail
{
    public function __construct(
        public string $householdId,
        public string $memberId,
    ) {
    }
}
