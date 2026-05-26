<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

final readonly class LinkItem
{
    public function __construct(
        public string $masterItemId,
        public string $linkedItemId,
        public int $reservedQuantityUnits,
        public bool $unlimited,
        public int $minRequiredUnits,
        public int $maxPerPurchaseUnits,
        public ?string $includeUntilIso,
    ) {
    }
}
