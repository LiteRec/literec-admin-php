<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\Quantity;
use App\Inventory\Domain\ValueObject\StockBatchId;
use DateTimeImmutable;

/**
 * Emitted once per StockBatch touched during a return.
 *
 * Carries the batch's cost-basis so the COGS rollup can reverse the
 * matching sale movement without rejoining the batches table.
 */
final readonly class StockReturned
{
    public function __construct(
        public InventoryItemId $inventoryItemId,
        public StockBatchId $stockBatchId,
        public Quantity $quantityRestored,
        public CostPerUnit $costPerUnit,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
