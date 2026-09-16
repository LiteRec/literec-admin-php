<?php

declare(strict_types=1);

namespace App\Households\Application\Command;

use App\Households\Domain\Households;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * A read-only load of the target household plus a same-transaction row
 * lock on the member being shared are not enough on their own to rule
 * out two races: (a) a concurrent merge or deactivation of the member
 * between this handler's load and its write, and (b) two concurrent link
 * requests for the same (member, household) pair. {@see Households::lockUnmergedMember()}
 * closes (a) the same way {@see MergeMembersHandler} does — a row lock
 * taken immediately before {@see Households::save()}, inside this
 * handler's transaction, re-asserting the member is still unmerged. (b)
 * is closed by `household_member_affiliations`' primary key, which
 * {@see \App\Households\Infrastructure\Persistence\Doctrine\DoctrineHouseholds::save()}
 * translates into {@see \App\Households\Domain\Exception\HouseholdAlreadyLinked}.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class LinkMinorToHouseholdHandler
{
    use ReleasesHouseholdEvents;

    public function __construct(
        private readonly Households $households,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    private function eventBus(): MessageBusInterface // NOSONAR
    {
        return $this->eventBus;
    }

    public function __invoke(LinkMinorToHousehold $command): void
    {
        $target = HouseholdId::fromString($command->householdId);
        // Loaded only to confirm the target household exists; never
        // mutated — a single transaction touches at most one aggregate.
        $this->households->findById($target);

        $memberId = MemberId::fromString($command->memberId);
        $home = $this->households->findByMemberId($memberId);

        $home->member($memberId)->shareWithHousehold($target, $this->clock);
        // Re-assert the not-merged premise under a row lock, in the same
        // transaction as the write below — see the class docblock.
        $this->households->lockUnmergedMember($home->id(), $memberId);
        $this->households->save($home);

        $this->releaseAndDispatch($home);
    }
}
