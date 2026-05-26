<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\ValueObject\InventoryItemId;
use DateTimeImmutable;

final readonly class InventoryItemRentableChanged
{
    public function __construct(
        public InventoryItemId $inventoryItemId,
        public bool $rentable,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
