<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\View;

/**
 * Paginated projection result for {@see App\Inventory\Application\Query\GetStockMovementHistory}.
 */
final readonly class StockMovementPage
{
    /**
     * @param list<StockMovementView> $movements
     */
    public function __construct(
        public array $movements,
        public int $totalCount,
        public int $pageNumber,
        public int $pageSize,
    ) {
    }
}
