<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

use App\Inventory\Domain\InventoryItems;
use App\Inventory\Domain\ItemGroups;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\ItemGroupId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
final class AddItemToGroupHandler
{
    public function __construct(
        private readonly ItemGroups $itemGroups,
        private readonly InventoryItems $inventoryItems,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(AddItemToGroup $command): void
    {
        $itemId = InventoryItemId::fromString($command->inventoryItemId);
        // Existence check on the InventoryItem — throws InventoryItemNotFound
        // before mutating the group so a typo'd id does not leave the group
        // in a half-updated state.
        $this->inventoryItems->byId($itemId);

        $group = $this->itemGroups->byId(ItemGroupId::fromString($command->itemGroupId));
        $group->addItem($itemId, $this->clock);

        $this->itemGroups->save($group);

        foreach ($group->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
