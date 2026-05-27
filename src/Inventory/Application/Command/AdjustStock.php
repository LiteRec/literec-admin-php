<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

/**
 * Primitive-only command DTO for the AdjustStock use case (Take Inventory
 * bulk count).
 *
 * Bulk-take is the UI primitive (LRA-87); the handler accepts one tuple
 * per invocation. The UI dispatches N commands inside a single HTTP
 * request — each command its own transaction, so partial failures of
 * one item do not roll back the others.
 *
 * `adjustmentSubReason` (optional, added by LRA-94) carries the variance
 * sub-category from {@see App\Inventory\Domain\ValueObject\StockAdjustmentReason}
 * as a string enum value. The handler validates the value and persists
 * it on the inventory_stock_movements ledger row alongside the
 * operator's free-text reason. Pre-LRA-94 callers may pass null; the
 * handler defaults to {@see App\Inventory\Domain\ValueObject\StockAdjustmentReason::OTHER}.
 */
final readonly class AdjustStock
{
    public function __construct(
        public string $itemId,
        public string $facilityCode,
        public int $targetQuantityUnits,
        public string $reason,
        public ?string $adjustmentSubReason = null,
    ) {
    }
}
