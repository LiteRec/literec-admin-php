<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\Quantity;
use App\Inventory\Domain\ValueObject\StockBatchId;
use App\Inventory\Domain\ValueObject\StockMovementReason;
use DateTimeImmutable;

/**
 * Emitted once per StockBatch touched during a consume operation.
 *
 * Carries the cost-basis of the batch so the downstream COGS projection
 * can roll up cost-of-sales without rejoining the batches table.
 */
final readonly class StockMovementRecorded
{
    public function __construct(
        public InventoryItemId $inventoryItemId,
        public StockBatchId $stockBatchId,
        public Quantity $quantityConsumed,
        public CostPerUnit $costPerUnit,
        public StockMovementReason $reason,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
