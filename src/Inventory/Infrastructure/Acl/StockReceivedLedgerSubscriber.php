<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Acl;

use App\Inventory\Domain\Event\StockReceived;
use App\Inventory\Domain\ValueObject\StockMovementReason;
use App\Inventory\Domain\StockMovementLedger;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Appends one inventory_stock_movements row (kind=RECEIVED) for every
 * {@see StockReceived} domain event.
 *
 * The reason field distinguishes manual receives from purchase-order
 * receipts: `sourceLineId` is set by {@see App\Inventory\Domain\InventoryItem::receiveBatch()}
 * only when the receive originated from {@see App\Inventory\Application\Command\ReceivePurchaseOrderLineHandler}.
 * The LRA-91 Entry Log report relies on this distinction to filter
 * receipt-flow without joining purchase orders back in.
 */
#[AsMessageHandler(bus: 'event.bus')]
final readonly class StockReceivedLedgerSubscriber
{
    public function __construct(private StockMovementLedger $ledger)
    {
    }

    public function __invoke(StockReceived $event): void
    {
        $reason = $event->sourceLineId === null
            ? StockMovementReason::RECEIPT
            : StockMovementReason::PO_RECEIPT;

        $this->ledger->recordReceived(
            itemId: $event->inventoryItemId,
            facilityCode: $event->facilityCode,
            stockBatchId: $event->stockBatchId,
            reason: $reason,
            quantity: $event->quantity,
            costPerUnit: $event->costPerUnit,
            recordedAt: $event->occurredAt,
            operatorNote: $event->comments?->value,
        );
    }
}
