<?php

declare(strict_types=1);

namespace App\Inventory\Domain\ValueObject;

/**
 * Inputs for one line in a {@see App\Inventory\Domain\PurchaseOrder::createDraft()}
 * call.
 *
 * Final readonly so callers cannot mutate the draft between assembly and
 * the aggregate factory; the aggregate copies each draft into a
 * {@see App\Inventory\Domain\PurchaseOrderLine} child entity at
 * construction time.
 */
final readonly class PurchaseOrderLineDraft
{
    public function __construct(
        public PurchaseOrderLineId $lineId,
        public InventoryItemId $itemId,
        public Quantity $orderedQuantity,
        public CostPerUnit $costPerUnit,
    ) {
    }
}
