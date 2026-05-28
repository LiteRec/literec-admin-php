<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\Report;

/**
 * Paginated projection result for {@see \App\Inventory\Application\Query\Port\InventoryReadModel::entryLog()}.
 */
final readonly class EntryLogPage
{
    /**
     * @param list<EntryLogRowView> $rows
     */
    public function __construct(
        public array $rows,
        public int $totalCount,
        public int $pageNumber,
        public int $pageSize,
    ) {
    }
}
