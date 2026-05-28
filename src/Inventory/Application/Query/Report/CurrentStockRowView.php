<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\Report;

/**
 * One row of the Current Stock report projection (LRA-91). Produced by
 * {@see \App\Inventory\Application\Query\Port\InventoryReadModel::currentStock()}
 * and consumed by the dashboard card + CSV export.
 */
final readonly class CurrentStockRowView
{
    public function __construct(
        public string $inventoryItemId,
        public string $listingCode,
        public string $name,
        public string $kind,
        public string $facilityCode,
        public int $onHandUnits,
        public int $reorderThresholdUnits,
        public bool $isAtOrBelowThreshold,
    ) {
    }
}
