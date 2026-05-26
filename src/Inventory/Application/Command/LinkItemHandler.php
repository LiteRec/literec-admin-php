<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

use App\Inventory\Domain\Exception\DuplicateItemLink;
use App\Inventory\Domain\IdentityGenerator;
use App\Inventory\Domain\InventoryItems;
use App\Inventory\Domain\ItemLink;
use App\Inventory\Domain\ItemLinks;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\ItemLinkId;
use App\Inventory\Domain\ValueObject\Quantity;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
final class LinkItemHandler
{
    public function __construct(
        private readonly ItemLinks $itemLinks,
        private readonly InventoryItems $inventoryItems,
        private readonly IdentityGenerator $ids,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(LinkItem $command): ItemLinkId
    {
        $masterId = InventoryItemId::fromString($command->masterItemId);
        $linkedId = InventoryItemId::fromString($command->linkedItemId);

        // Existence checks on both items before mutating state.
        $this->inventoryItems->byId($masterId);
        $this->inventoryItems->byId($linkedId);

        if ($this->itemLinks->existsForPair($masterId, $linkedId)) {
            throw DuplicateItemLink::forPair($masterId, $linkedId);
        }

        $id = $this->ids->nextItemLinkId();
        $link = ItemLink::link(
            $id,
            $masterId,
            $linkedId,
            Quantity::ofUnits($command->reservedQuantityUnits),
            $command->unlimited,
            Quantity::ofUnits($command->minRequiredUnits),
            Quantity::ofUnits($command->maxPerPurchaseUnits),
            $command->includeUntilIso !== null ? new DateTimeImmutable($command->includeUntilIso) : null,
            $this->clock,
        );

        $this->itemLinks->add($link);

        foreach ($link->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }

        return $id;
    }
}
