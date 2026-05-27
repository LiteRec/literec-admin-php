<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\View;

/**
 * Paginated projection result for the LRA-90
 * {@see \App\Inventory\Application\Query\ListPurchaseOrders} query.
 */
final readonly class PurchaseOrderListPage
{
    /**
     * @param list<PurchaseOrderSummaryView> $items
     */
    public function __construct(
        public array $items,
        public int $totalCount,
        public int $pageNumber,
        public int $pageSize,
    ) {
    }
}
