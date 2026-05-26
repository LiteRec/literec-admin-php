<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\View;

/**
 * Per-facility stock block on an {@see InventoryItemDetailView}.
 *
 * Batches are listed in FIFO order (oldest receivedAt first, with
 * StockBatchId as the deterministic tiebreaker).
 */
final readonly class FacilityStockBlockView
{
    /**
     * @param list<StockBatchView> $batches FIFO-ordered.
     */
    public function __construct(
        public string $facilityCode,
        public int $totalOnHandUnits,
        public array $batches,
    ) {
    }
}
