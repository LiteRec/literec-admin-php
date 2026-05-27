<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query;

use DateTimeImmutable;

/**
 * Primitive criteria DTO for the LRA-88 movement history page and the
 * LRA-91 Entry Log report.
 *
 * inventoryItemId is nullable so the LRA-91 Entry Log report — which
 * scans every item at a facility — can pass null and let the read
 * model project across all items. The LRA-88 movement history page
 * always supplies the item id (the page is scoped to one item).
 */
final readonly class GetStockMovementHistory
{
    public function __construct(
        public ?string $inventoryItemId = null,
        public ?string $facilityCode = null,
        public ?DateTimeImmutable $dateFrom = null,
        public ?DateTimeImmutable $dateTo = null,
        public ?string $kind = null,
        public ?string $reason = null,
        public int $pageNumber = 1,
        public int $pageSize = 50,
    ) {
    }
}
