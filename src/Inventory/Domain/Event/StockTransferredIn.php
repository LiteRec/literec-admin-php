<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\TransferLineItem;
use DateTimeImmutable;

/**
 * Emitted once per InventoryItem::transferStock() at the destination side.
 *
 * Carries one {@see TransferLineItem} per freshly-created destination
 * batch — each receives the cost-per-unit from its matching source slice
 * verbatim (no averaging) so cost basis survives the move.
 */
final readonly class StockTransferredIn
{
    /**
     * @param list<TransferLineItem> $lineItems
     */
    public function __construct(
        public InventoryItemId $inventoryItemId,
        public FacilityCode $fromFacility,
        public FacilityCode $toFacility,
        public array $lineItems,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
