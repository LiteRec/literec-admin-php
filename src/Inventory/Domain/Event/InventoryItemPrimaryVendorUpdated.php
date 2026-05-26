<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\VendorId;
use DateTimeImmutable;

final readonly class InventoryItemPrimaryVendorUpdated
{
    public function __construct(
        public InventoryItemId $inventoryItemId,
        public ?VendorId $primaryVendorId,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
