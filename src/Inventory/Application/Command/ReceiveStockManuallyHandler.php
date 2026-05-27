<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

use App\Inventory\Application\WrapsOptimisticLock;
use App\Inventory\Domain\IdentityGenerator;
use App\Inventory\Domain\InventoryItems;
use App\Inventory\Domain\ValueObject\Comment;
use App\Inventory\Domain\ValueObject\CostPerUnit;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\PurchaseOrderLineId;
use App\Inventory\Domain\ValueObject\Quantity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
final class ReceiveStockManuallyHandler
{
    use WrapsOptimisticLock;

    public function __construct(
        private readonly InventoryItems $inventoryItems,
        private readonly IdentityGenerator $ids,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(ReceiveStockManually $command): void
    {
        $itemId = InventoryItemId::fromString($command->itemId);
        $item = $this->inventoryItems->byId($itemId);

        $item->receiveBatch(
            FacilityCode::fromString($command->facilityCode),
            Quantity::ofUnits($command->quantityUnits),
            CostPerUnit::ofCents($command->costPerUnitCents),
            $command->purchaseOrderLineId !== null
                ? PurchaseOrderLineId::fromString($command->purchaseOrderLineId)
                : null,
            $command->comment !== null ? Comment::of($command->comment) : null,
            $this->ids->nextStockBatchId(),
            $this->clock,
        );

        $this->wrapInventoryItemSave($itemId, fn () => $this->inventoryItems->save($item));

        foreach ($item->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
