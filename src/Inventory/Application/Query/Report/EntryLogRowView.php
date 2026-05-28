<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\Report;

use DateTimeImmutable;

/**
 * One row of the Entry Log projection (LRA-91). Projects directly from
 * the inventory_stock_movements ledger joined to inventory_items and
 * catalog_listings so the report can render the human-friendly listing
 * code without a follow-up query.
 */
final readonly class EntryLogRowView
{
    public function __construct(
        public string $movementId,
        public string $inventoryItemId,
        public string $listingCode,
        public string $facilityCode,
        public string $kind,
        public string $reason,
        public int $quantity,
        public int $costPerUnitCents,
        public ?string $operatorNote,
        public DateTimeImmutable $recordedAt,
    ) {
    }
}
