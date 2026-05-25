<?php

declare(strict_types=1);

namespace App\Catalog\Application\Command;

use App\Catalog\Domain\Listings;
use App\Catalog\Domain\ValueObject\ListingId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
final class ArchiveListingHandler
{
    public function __construct(
        private readonly Listings $listings,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(ArchiveListing $command): void
    {
        $listing = $this->listings->byId(ListingId::fromString($command->listingId));
        $listing->archive($this->clock);
        $this->listings->save($listing);

        foreach ($listing->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
