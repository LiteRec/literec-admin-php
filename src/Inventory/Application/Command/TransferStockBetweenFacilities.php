<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

/**
 * Primitive-only command DTO for the TransferStockBetweenFacilities use case.
 *
 * Moves units of one InventoryItem from a source facility to a
 * destination facility, preserving cost-basis per source batch. Zero
 * units is a no-op; negative units throw via the {@see Quantity} VO at
 * the handler boundary. Source/destination facilities must differ
 * (enforced by {@see App\Inventory\Domain\InventoryItem::transferStock()}).
 */
final readonly class TransferStockBetweenFacilities
{
    public function __construct(
        public string $itemId,
        public string $fromFacilityCode,
        public string $toFacilityCode,
        public int $quantityUnits,
    ) {
    }
}
