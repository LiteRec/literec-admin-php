<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PosColor;
use App\Inventory\Domain\ValueObject\ReorderThreshold;
use App\Inventory\Domain\ValueObject\VendorId;
use DateTimeImmutable;

final readonly class InventoryItemRegistered
{
    public function __construct(
        public InventoryItemId $inventoryItemId,
        public ListingId $listingId,
        public ?VendorId $primaryVendorId,
        public PosColor $posColor,
        public bool $trackInventory,
        public bool $rentable,
        public ReorderThreshold $reorderThreshold,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
