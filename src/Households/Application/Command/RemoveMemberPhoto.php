<?php

declare(strict_types=1);

namespace App\Households\Application\Command;

/**
 * Command bus message for removing a member's uploaded profile photo
 * (LRA-207).
 */
final readonly class RemoveMemberPhoto
{
    public function __construct(
        public string $householdId,
        public string $memberId,
    ) {
    }
}
