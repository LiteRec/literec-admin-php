<?php

declare(strict_types=1);

namespace App\Households\Application\Command;

/**
 * Primitive-only command DTO withdrawing a previously-created share of a
 * minor member from a household (LRA-210).
 */
final readonly class WithdrawMinorFromHousehold
{
    public function __construct(
        public string $householdId,
        public string $memberId,
    ) {
    }
}
