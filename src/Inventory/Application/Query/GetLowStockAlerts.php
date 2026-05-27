<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query;

/**
 * Primitive criteria DTO for the LRA-91 Low Stock Alerts report card.
 * Empty facilityCode evaluates across every facility (per-item
 * thresholds are still per-facility — the alert fires when ANY
 * facility's stock drops below that facility's threshold).
 */
final readonly class GetLowStockAlerts
{
    public function __construct(
        public ?string $facilityCode = null,
    ) {
    }
}
