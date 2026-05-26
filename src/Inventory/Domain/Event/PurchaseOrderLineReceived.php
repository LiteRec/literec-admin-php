<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use App\Inventory\Domain\ValueObject\PurchaseOrderLineId;
use App\Inventory\Domain\ValueObject\Quantity;
use DateTimeImmutable;

/**
 * Emitted once per receiveLine() call.
 *
 * Carries every field the LRA-79 application service needs to create a
 * matching StockBatch on the referenced InventoryItem so the cross-
 * aggregate flow does not have to re-load the PurchaseOrder.
 */
final readonly class PurchaseOrderLineReceived
{
    public function __construct(
        public PurchaseOrderId $purchaseOrderId,
        public PurchaseOrderLineId $lineId,
        public InventoryItemId $itemId,
        public FacilityCode $facilityCode,
        public Quantity $quantityReceived,
        public CostPerUnit $costPerUnit,
        public DateTimeImmutable $receivedAt,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
