<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\Report;

use DateTimeImmutable;

/**
 * Primitive criteria DTO for the LRA-91 Entry Log report card.
 *
 * Projects every row of the inventory_stock_movements ledger that
 * matches the supplied filter — like
 * {@see \App\Inventory\Application\Query\GetStockMovementHistory} but
 * without the `inventoryItemId` requirement, because Entry Log is a
 * cross-item ledger view.
 */
final readonly class EntryLogReport
{
    public function __construct(
        public ?DateTimeImmutable $dateFrom = null,
        public ?DateTimeImmutable $dateTo = null,
        public ?string $facilityCode = null,
        public ?string $kind = null,
        public ?string $reason = null,
        public int $pageNumber = 1,
        public int $pageSize = 50,
    ) {
    }
}
