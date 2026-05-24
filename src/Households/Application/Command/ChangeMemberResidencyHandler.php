<?php

declare(strict_types=1);

namespace App\Households\Application\Command;

use App\Households\Domain\Exception\InvariantViolation;
use App\Households\Domain\Households;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\ResidencyStatus;
use DateMalformedStringException;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
final class ChangeMemberResidencyHandler
{
    public function __construct(
        private readonly Households $households,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(ChangeMemberResidency $command): void
    {
        $household = $this->households->findById(HouseholdId::fromString($command->householdId));
        $memberId = MemberId::fromString($command->memberId);
        $status = ResidencyStatus::from($command->residencyStatusCode);

        try {
            $effectiveFrom = new DateTimeImmutable($command->effectiveFromIso);
        } catch (DateMalformedStringException) {
            throw InvariantViolation::with('Residency effective-from date is not a valid ISO date.');
        }

        $household->setResidencyStatus($memberId, $status, $effectiveFrom, $this->clock, $command->reason);
        $this->households->save($household);

        foreach ($household->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
