<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

use App\Inventory\Application\WrapsOptimisticLock;
use App\Inventory\Domain\IdentityGenerator;
use App\Inventory\Domain\InventoryItems;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\Quantity;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
final class TransferStockBetweenFacilitiesHandler
{
    use WrapsOptimisticLock;

    public function __construct(
        private readonly InventoryItems $inventoryItems,
        private readonly IdentityGenerator $ids,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(TransferStockBetweenFacilities $command): void
    {
        $itemId = InventoryItemId::fromString($command->itemId);
        $item = $this->inventoryItems->byId($itemId);

        $item->transferStock(
            FacilityCode::fromString($command->fromFacilityCode),
            FacilityCode::fromString($command->toFacilityCode),
            Quantity::ofUnits($command->quantityUnits),
            $this->clock,
            $this->ids,
        );

        $this->wrapInventoryItemSave($itemId, fn () => $this->inventoryItems->save($item));

        foreach ($item->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
