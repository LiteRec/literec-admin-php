<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\ReorderThreshold;
use DateTimeImmutable;

final readonly class InventoryItemReorderThresholdUpdated
{
    public function __construct(
        public InventoryItemId $inventoryItemId,
        public ReorderThreshold $reorderThreshold,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
