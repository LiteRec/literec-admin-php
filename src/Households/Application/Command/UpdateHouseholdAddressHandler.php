<?php

declare(strict_types=1);

namespace App\Households\Application\Command;

use App\Households\Domain\Households;
use App\Households\Domain\ValueObject\Address;
use App\Households\Domain\ValueObject\HouseholdId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
final class UpdateHouseholdAddressHandler
{
    public function __construct(
        private readonly Households $households,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(UpdateHouseholdAddress $command): void
    {
        $household = $this->households->findById(HouseholdId::fromString($command->householdId));

        $address = Address::of(
            $command->street,
            $command->unit,
            $command->city,
            $command->state,
            $command->postalCode,
            $command->country,
        );

        $household->updateAddress($address, $this->clock);
        $this->households->save($household);

        foreach ($household->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
