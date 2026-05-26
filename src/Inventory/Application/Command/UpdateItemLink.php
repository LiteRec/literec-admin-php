<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

final readonly class UpdateItemLink
{
    public function __construct(
        public string $itemLinkId,
        public int $reservedQuantityUnits,
        public bool $unlimited,
        public int $minRequiredUnits,
        public int $maxPerPurchaseUnits,
        public ?string $includeUntilIso,
    ) {
    }
}
