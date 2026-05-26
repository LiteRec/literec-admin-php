<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\View;

use DateTimeImmutable;

final readonly class InventoryItemDetailView
{
    /**
     * @param list<FacilityStockBlockView> $facilityStockBlocks
     */
    public function __construct(
        public string $inventoryItemId,
        public string $listingId,
        public ?string $primaryVendorId,
        public string $posColorHex,
        public bool $tracksInventory,
        public bool $rentable,
        public ?int $reorderThresholdUnits,
        public bool $archived,
        public int $totalOnHandUnits,
        public array $facilityStockBlocks,
        public DateTimeImmutable $registeredAt,
        public DateTimeImmutable $updatedAt,
    ) {
    }
}
