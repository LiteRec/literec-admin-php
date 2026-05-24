<?php

declare(strict_types=1);

namespace App\Households\Application\Command;

final readonly class DeactivateMember
{
    public function __construct(
        public string $householdId,
        public string $memberId,
        public string $reason,
    ) {
    }
}
