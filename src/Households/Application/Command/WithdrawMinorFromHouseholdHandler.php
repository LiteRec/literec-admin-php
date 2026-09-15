<?php

declare(strict_types=1);

namespace App\Households\Application\Command;

use App\Households\Domain\Households;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

#[AsMessageHandler(bus: 'command.bus')]
final class WithdrawMinorFromHouseholdHandler
{
    use ReleasesHouseholdEvents;

    public function __construct(
        private readonly Households $households,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(WithdrawMinorFromHousehold $command): void
    {
        $target = HouseholdId::fromString($command->householdId);
        $memberId = MemberId::fromString($command->memberId);
        $home = $this->households->findByMemberId($memberId);

        $home->withdrawMemberFromHousehold($memberId, $target, $this->clock);
        $this->households->save($home);

        $this->releaseAndDispatch($home);
    }
}
