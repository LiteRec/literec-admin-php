<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

use App\Inventory\Domain\Event\PurchaseOrderLineReceived;
use App\Inventory\Domain\IdentityGenerator;
use App\Inventory\Domain\InventoryItems;
use App\Inventory\Domain\PurchaseOrders;
use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use App\Inventory\Domain\ValueObject\PurchaseOrderLineId;
use App\Inventory\Domain\ValueObject\Quantity;
use DateTimeImmutable;
use LogicException;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * Cross-aggregate handler: marks a PurchaseOrderLine as (partly or
 * fully) received AND creates one matching StockBatch on the referenced
 * InventoryItem at the PO's facility.
 *
 * Both mutations run inside the same `doctrine_transaction` middleware
 * activation on `command.bus`, so either both writes commit or both
 * roll back. The PO aggregate's PurchaseOrderLineReceived event
 * carries every field the InventoryItem flow needs (itemId, facility,
 * quantity, costPerUnit, receivedAt, sourceLineId) so the second leg
 * does not need to re-load the PO line.
 *
 * The append-only inventory_stock_movements ledger is intentionally NOT
 * written from this handler — its writer lands with the LRA-83 ACL
 * (which already subscribes to PurchaseOrderLineReceived from this
 * dispatch path). Splitting the ledger writer into its own handler
 * keeps this transaction focused on the two aggregate mutations.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class ReceivePurchaseOrderLineHandler
{
    public function __construct(
        private readonly PurchaseOrders $purchaseOrders,
        private readonly InventoryItems $inventoryItems,
        private readonly IdentityGenerator $ids,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(ReceivePurchaseOrderLine $command): void
    {
        $order = $this->purchaseOrders->byId(PurchaseOrderId::fromString($command->purchaseOrderId));

        $receivedAt = new DateTimeImmutable($command->receivedAtIso);
        $lineId = PurchaseOrderLineId::fromString($command->lineId);
        $quantity = Quantity::ofUnits($command->receivedQuantityUnits);

        $order->receiveLine($lineId, $quantity, $receivedAt, $this->clock);

        $this->purchaseOrders->save($order);

        // Find the PurchaseOrderLineReceived event the aggregate just buffered
        // so we have the canonical itemId, facility, costPerUnit, etc.
        $releasedFromOrder = $order->releaseEvents();
        $lineReceived = null;
        foreach ($releasedFromOrder as $event) {
            if ($event instanceof PurchaseOrderLineReceived && $event->lineId->equals($lineId)) {
                $lineReceived = $event;
                break;
            }
        }
        if ($lineReceived === null) {
            throw new LogicException(
                'PurchaseOrder::receiveLine() did not record a matching PurchaseOrderLineReceived event.',
            );
        }

        $item = $this->inventoryItems->byId($lineReceived->itemId);

        $item->receiveBatch(
            $lineReceived->facilityCode,
            $lineReceived->quantityReceived,
            $lineReceived->costPerUnit,
            $lineId,
            null,
            $this->ids->nextStockBatchId(),
            $this->clock,
        );

        $this->inventoryItems->save($item);

        $releasedFromItem = $item->releaseEvents();

        foreach ([...$releasedFromOrder, ...$releasedFromItem] as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
