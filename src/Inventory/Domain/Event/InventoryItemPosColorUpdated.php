<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PosColor;
use DateTimeImmutable;

final readonly class InventoryItemPosColorUpdated
{
    public function __construct(
        public InventoryItemId $inventoryItemId,
        public PosColor $posColor,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
