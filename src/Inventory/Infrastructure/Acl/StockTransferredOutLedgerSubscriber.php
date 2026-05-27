<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Acl;

use App\Inventory\Domain\Event\StockTransferredOut;
use App\Inventory\Domain\StockMovementLedger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Appends one inventory_stock_movements row per touched source batch
 * (kind=TRANSFERRED_OUT, reason=TRANSFER_OUT) for every
 * {@see StockTransferredOut} domain event.
 *
 * The matching destination rows are written by
 * {@see StockTransferredInLedgerSubscriber} from the paired
 * {@see App\Inventory\Domain\Event\StockTransferredIn} event so the
 * source/destination pair lines up batch-for-batch in the ledger.
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class StockTransferredOutLedgerSubscriber
{
    public function __construct(private StockMovementLedger $ledger)
    {
    }

    public function __invoke(StockTransferredOut $event): void
    {
        foreach ($event->lineItems as $line) {
            $this->ledger->recordTransferredOut(
                itemId: $event->inventoryItemId,
                facilityCode: $event->fromFacility,
                stockBatchId: $line->stockBatchId,
                quantity: $line->quantity,
                costPerUnit: $line->costPerUnit,
                recordedAt: $event->occurredAt,
            );
        }
    }
}
