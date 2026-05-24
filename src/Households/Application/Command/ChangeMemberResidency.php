<?php

declare(strict_types=1);

namespace App\Households\Application\Command;

final readonly class ChangeMemberResidency
{
    public function __construct(
        public string $householdId,
        public string $memberId,
        public string $residencyStatusCode,
        public string $effectiveFromIso,
        public ?string $reason = null,
    ) {
    }
}
