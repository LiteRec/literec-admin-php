<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

use App\Inventory\Domain\ItemGroups;
use App\Inventory\Domain\ValueObject\ItemGroupId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
final class ArchiveItemGroupHandler
{
    public function __construct(
        private readonly ItemGroups $itemGroups,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(ArchiveItemGroup $command): void
    {
        $group = $this->itemGroups->byId(ItemGroupId::fromString($command->itemGroupId));
        $group->archive($this->clock);

        $this->itemGroups->save($group);

        foreach ($group->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
