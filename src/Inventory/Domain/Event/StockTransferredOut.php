<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\TransferLineItem;
use DateTimeImmutable;

/**
 * Emitted once per InventoryItem::transferStock() at the source side.
 *
 * Carries one {@see TransferLineItem} per source batch touched so the
 * destination event {@see StockTransferredIn} and the per-batch
 * StockMovementRecorded entries (with reason TRANSFER_OUT) line up.
 */
final readonly class StockTransferredOut
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
