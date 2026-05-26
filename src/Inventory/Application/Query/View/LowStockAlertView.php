<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\View;

/**
 * One row of the low-stock alerts projection. An item appears here
 * only when its on-hand quantity at the named facility is below its
 * reorder threshold; rows are sorted by {@see $shortfallUnits} DESC so
 * the most urgent restocks bubble to the top.
 */
final readonly class LowStockAlertView
{
    public function __construct(
        public string $inventoryItemId,
        public string $listingId,
        public string $facilityCode,
        public int $currentOnHandUnits,
        public int $reorderThresholdUnits,
        public int $shortfallUnits,
        public ?string $primaryVendorId,
    ) {
    }
}
