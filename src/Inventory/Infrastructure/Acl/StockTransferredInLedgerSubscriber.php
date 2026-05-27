<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Acl;

use App\Inventory\Domain\Event\StockTransferredIn;
use App\Inventory\Domain\StockMovementLedger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Appends one inventory_stock_movements row per freshly-created
 * destination batch (kind=TRANSFERRED_IN, reason=TRANSFER_IN) for
 * every {@see StockTransferredIn} domain event.
 *
 * Cost-per-unit is preserved verbatim from the source slice via the
 * {@see App\Inventory\Domain\ValueObject\TransferLineItem} payload so
 * the ledger reflects the exact basis the batch carries on hand.
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class StockTransferredInLedgerSubscriber
{
    public function __construct(private StockMovementLedger $ledger)
    {
    }

    public function __invoke(StockTransferredIn $event): void
    {
        foreach ($event->lineItems as $line) {
            $this->ledger->recordTransferredIn(
                itemId: $event->inventoryItemId,
                facilityCode: $event->toFacility,
                stockBatchId: $line->stockBatchId,
                quantity: $line->quantity,
                costPerUnit: $line->costPerUnit,
                recordedAt: $event->occurredAt,
            );
        }
    }
}
