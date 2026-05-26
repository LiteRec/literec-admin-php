<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\ItemGroupId;
use DateTimeImmutable;

final readonly class ItemAddedToGroup
{
    public function __construct(
        public ItemGroupId $itemGroupId,
        public InventoryItemId $itemId,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
