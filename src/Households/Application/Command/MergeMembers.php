<?php

declare(strict_types=1);

namespace App\Households\Application\Command;

final readonly class MergeMembers
{
    public function __construct(
        public string $survivorHouseholdId,
        public string $survivorMemberId,
        public string $duplicateMemberId,
    ) {
    }
}
