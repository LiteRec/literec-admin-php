<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\View;

use DateTimeImmutable;

/**
 * One row of the stock movement history projection (LRA-97, consumed
 * by LRA-88 + LRA-91). Projects directly from the
 * inventory_stock_movements ledger introduced in LRA-94 — no aggregate
 * traversal, no UnitOfWork.
 */
final readonly class StockMovementView
{
    public function __construct(
        public string $movementId,
        public string $inventoryItemId,
        public string $facilityCode,
        public ?string $stockBatchId,
        public string $kind,
        public string $reason,
        public int $quantity,
        public int $costPerUnitCents,
        public ?string $operatorNote,
        public ?string $transactionId,
        public ?string $listingId,
        public DateTimeImmutable $recordedAt,
    ) {
    }
}
