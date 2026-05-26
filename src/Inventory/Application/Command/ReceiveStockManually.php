<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

/**
 * Primitive-only command DTO for the ReceiveStockManually use case.
 *
 * Records a vendor receipt entered directly by an admin (no Purchase
 * Order). Value-object construction happens inside the handler so bad
 * input surfaces as a named domain exception instead of a TypeError at
 * the bus boundary.
 */
final readonly class ReceiveStockManually
{
    public function __construct(
        public string $itemId,
        public string $facilityCode,
        public int $quantityUnits,
        public int $costPerUnitCents,
        public ?string $comment,
        public ?string $purchaseOrderLineId,
    ) {
    }
}
