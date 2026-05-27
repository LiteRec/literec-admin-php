<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Acl;

use App\Inventory\Domain\Event\StockReturned;
use App\Inventory\Domain\StockMovementLedger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Appends one inventory_stock_movements row (kind=RETURNED, reason=RETURN)
 * for every {@see StockReturned} domain event.
 *
 * Returns carry no transaction_id (the originating sale does, but the
 * return path is operator-initiated and reverses stock independently),
 * so the partial unique index on (transaction_id, item_id, facility_code)
 * does not apply here.
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class StockReturnedLedgerSubscriber
{
    public function __construct(private StockMovementLedger $ledger)
    {
    }

    public function __invoke(StockReturned $event): void
    {
        $this->ledger->recordReturned(
            itemId: $event->inventoryItemId,
            facilityCode: $event->facilityCode,
            stockBatchId: $event->stockBatchId,
            quantity: $event->quantityRestored,
            costPerUnit: $event->costPerUnit,
            recordedAt: $event->occurredAt,
        );
    }
}
