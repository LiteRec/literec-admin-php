<?php

declare(strict_types=1);

namespace App\Households\Application\Command;

use App\Households\Domain\Households;
use App\Households\Domain\MemberMergePolicy;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * Merges a duplicate member into a survivor (LRA-208).
 *
 * Only the duplicate's owning {@see \App\Households\Domain\Household}
 * aggregate is mutated and saved — the survivor-side invariant (the
 * survivor exists and is not itself merged) is asserted read-only against
 * the survivor's aggregate via {@see MemberMergePolicy} before the
 * duplicate's aggregate is touched, keeping this handler to a single
 * aggregate transaction.
 *
 * That read-only check alone is not enough to rule out a race: two
 * concurrent merges naming each other as survivor (X→Y and Y→X) could
 * both pass it before either commits, producing a merge cycle. Households::
 * lockUnmergedMember() closes that window by taking a row lock on the
 * survivor and re-asserting the same premise inside this handler's
 * transaction, immediately before the duplicate's row is written — so the
 * second writer either blocks and then observes the row already merged,
 * or is chosen as the deadlock victim.
 */
#[AsMessageHandler(bus: 'command.bus')]
final class MergeMembersHandler
{
    public function __construct(
        private readonly Households $households,
        private readonly MemberMergePolicy $policy,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(MergeMembers $command): void
    {
        $survivorHousehold = $this->households->findById(HouseholdId::fromString($command->survivorHouseholdId));
        $survivorId = MemberId::fromString($command->survivorMemberId);
        $duplicateId = MemberId::fromString($command->duplicateMemberId);

        $this->policy->assertSurvivorAccepts($survivorHousehold, $survivorId);

        $duplicateHousehold = $this->households->findByMemberId($duplicateId);
        $duplicateHousehold->mergeMemberInto($duplicateId, $survivorHousehold->id(), $survivorId, $this->clock);
        // Re-assert the survivor premise under a row lock, in the same
        // transaction as the write below — see the class docblock.
        $this->households->lockUnmergedMember($survivorHousehold->id(), $survivorId);
        $this->households->save($duplicateHousehold);

        foreach ($duplicateHousehold->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
