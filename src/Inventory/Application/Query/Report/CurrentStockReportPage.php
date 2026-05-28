<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\Report;

/**
 * Projection result for {@see \App\Inventory\Application\Query\Port\InventoryReadModel::currentStock()}.
 * Current Stock is not paginated — the report card shows the full
 * filtered set — but the DTO carries `totalCount` so the card heading
 * can render "N rows" consistently with the other reports.
 */
final readonly class CurrentStockReportPage
{
    /**
     * @param list<CurrentStockRowView> $items
     */
    public function __construct(
        public array $items,
        public int $totalCount,
    ) {
    }
}
