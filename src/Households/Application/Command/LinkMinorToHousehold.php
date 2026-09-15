<?php

declare(strict_types=1);

namespace App\Households\Application\Command;

/**
 * Primitive-only command DTO sharing a minor member (identified by their
 * home household membership) with another household (LRA-210).
 */
final readonly class LinkMinorToHousehold
{
    public function __construct(
        public string $householdId,
        public string $memberId,
    ) {
    }
}
