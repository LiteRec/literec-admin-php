<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

use App\Inventory\Domain\IdentityGenerator;
use App\Inventory\Domain\InventoryItems;
use App\Inventory\Domain\PurchaseOrder;
use App\Inventory\Domain\PurchaseOrders;
use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use App\Inventory\Domain\ValueObject\PurchaseOrderLineDraft;
use App\Inventory\Domain\ValueObject\Quantity;
use App\Inventory\Domain\ValueObject\VendorId;
use App\Inventory\Domain\Vendors;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * Drafts a new PurchaseOrder with N lines.
 *
 * Validates that the vendor exists (throws VendorNotFound from the LRA-72
 * port) and that every referenced inventory item exists (throws
 * InventoryItemNotFound). Both are read-only checks; the only aggregate
 * mutation inside this handler is the PurchaseOrder::createDraft()
 * factory, so the doctrine_transaction middleware's one-aggregate-per-
 * transaction invariant holds.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class CreatePurchaseOrderHandler
{
    public function __construct(
        private readonly PurchaseOrders $purchaseOrders,
        private readonly Vendors $vendors,
        private readonly InventoryItems $inventoryItems,
        private readonly IdentityGenerator $ids,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(CreatePurchaseOrder $command): PurchaseOrderId
    {
        $vendorId = VendorId::fromString($command->vendorId);
        $this->vendors->byId($vendorId);

        $facility = FacilityCode::fromString($command->facilityCode);

        $lineDrafts = [];
        foreach ($command->lines as $line) {
            $itemId = InventoryItemId::fromString($line['itemId']);
            $this->inventoryItems->byId($itemId);

            $lineDrafts[] = new PurchaseOrderLineDraft(
                $this->ids->nextPurchaseOrderLineId(),
                $itemId,
                Quantity::ofUnits($line['orderedQuantityUnits']),
                CostPerUnit::ofCents($line['costPerUnitCents']),
            );
        }

        $orderId = $this->ids->nextPurchaseOrderId();
        $order = PurchaseOrder::createDraft($orderId, $vendorId, $facility, $lineDrafts, $this->clock);

        $this->purchaseOrders->add($order);

        foreach ($order->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }

        return $orderId;
    }
}
