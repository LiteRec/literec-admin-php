<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\ValueObject\InventoryItemId;
use DateTimeImmutable;

final readonly class InventoryItemArchived
{
    public function __construct(
        public InventoryItemId $inventoryItemId,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
