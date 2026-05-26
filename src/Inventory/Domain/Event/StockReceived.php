<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\ValueObject\Comment;
use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PurchaseOrderLineId;
use App\Inventory\Domain\ValueObject\Quantity;
use App\Inventory\Domain\ValueObject\StockBatchId;
use DateTimeImmutable;

/**
 * Emitted once per StockBatch received.
 *
 * Carries the cost-basis stamped at receipt and the optional source PO
 * line so downstream projections (COGS, movement history) can attribute
 * the receipt without rejoining purchase orders.
 */
final readonly class StockReceived
{
    public function __construct(
        public InventoryItemId $inventoryItemId,
        public StockBatchId $stockBatchId,
        public FacilityCode $facilityCode,
        public Quantity $quantity,
        public CostPerUnit $costPerUnit,
        public ?PurchaseOrderLineId $sourceLineId,
        public ?Comment $comments,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
