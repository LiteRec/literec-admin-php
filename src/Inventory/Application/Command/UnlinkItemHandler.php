<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

use App\Inventory\Domain\ItemLinks;
use App\Inventory\Domain\ValueObject\ItemLinkId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
final class UnlinkItemHandler
{
    public function __construct(
        private readonly ItemLinks $itemLinks,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(UnlinkItem $command): void
    {
        $link = $this->itemLinks->byId(ItemLinkId::fromString($command->itemLinkId));
        $link->unlink($this->clock);

        $this->itemLinks->remove($link);

        foreach ($link->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
