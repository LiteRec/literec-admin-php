<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

/**
 * Primitive-only command DTO for the ReceivePurchaseOrderLine use case.
 *
 * Does NOT carry cost or itemId — both come from the PO line itself so
 * the caller cannot drift the cost basis of the resulting StockBatch.
 */
final readonly class ReceivePurchaseOrderLine
{
    public function __construct(
        public string $purchaseOrderId,
        public string $lineId,
        public int $receivedQuantityUnits,
        public string $receivedAtIso,
    ) {
    }
}
