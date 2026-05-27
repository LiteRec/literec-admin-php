<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\View;

/**
 * Paginated projection result for {@see App\Inventory\Application\Query\ListInventory}.
 * Returned by the handler and consumed by the LRA-85 list page.
 */
final readonly class InventoryListPage
{
    /**
     * @param list<InventorySummaryView> $items
     */
    public function __construct(
        public array $items,
        public int $totalCount,
        public int $pageNumber,
        public int $pageSize,
    ) {
    }
}
