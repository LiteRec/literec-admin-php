<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\View;

/**
 * Read-side projection of a single {@see \App\Inventory\Domain\PurchaseOrderLine}
 * for the LRA-90 Purchase Order detail page.
 *
 * Carries `remainingUnits` (ordered - received) and `isFullyReceived`
 * pre-computed so the template stays free of arithmetic.
 */
final readonly class PurchaseOrderLineDetailView
{
    public function __construct(
        public string $lineId,
        public string $itemId,
        public int $orderedUnits,
        public int $receivedUnits,
        public int $remainingUnits,
        public int $costPerUnitCents,
        public bool $isFullyReceived,
    ) {
    }
}
