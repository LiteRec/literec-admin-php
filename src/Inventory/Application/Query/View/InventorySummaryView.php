<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\View;

/**
 * One row of the Inventory list projection (LRA-97, consumed by
 * LRA-85). totalQuantityOnHand sums the on-hand stock across every
 * facility the item is held at when no facility filter is supplied,
 * or the requested facility's total when one is.
 *
 * groupNames is the projected list of ItemGroup names the item
 * belongs to at the time of the query; an empty list means the item
 * is not categorised.
 */
final readonly class InventorySummaryView
{
    /**
     * @param list<string> $groupNames
     */
    public function __construct(
        public string $inventoryItemId,
        public string $listingId,
        public string $listingCode,
        public string $name,
        public string $kind,
        public int $totalQuantityOnHand,
        public int $reorderThresholdUnits,
        public bool $archived,
        public array $groupNames,
    ) {
    }
}
