<?php

declare(strict_types=1);

namespace App\Households\Application\Event;

use App\Households\Domain\Event\MemberMergedInto;
use App\Households\Domain\Households;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

/**
 * Fills the survivor's blank email/phone from the duplicate's contact
 * fields carried on {@see MemberMergedInto} — the legacy merge's contact
 * gap-fill (LRA-208).
 *
 * Loads and saves the survivor's owning {@see \App\Households\Domain\Household}
 * aggregate in its own flush, satisfying "modify at most one aggregate per
 * transaction": {@see \App\Households\Application\Command\MergeMembersHandler}
 * already committed the duplicate's aggregate change before this handler
 * runs (dispatched post-commit via DispatchAfterCurrentBusStamp on the
 * originating command). A same-household merge loads the same Household
 * instance a second time here; that is harmless.
 */
#[AsMessageHandler(bus: 'event.bus')]
final class FillSurvivorContactFromMergedMemberHandler
{
    public function __construct(
        private readonly Households $households,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(MemberMergedInto $event): void
    {
        $survivorHousehold = $this->households->findById($event->survivorHouseholdId);

        $survivorHousehold->member($event->survivorMemberId)->fillContactGaps(
            $event->email,
            $event->phone,
            $this->clock,
        );
        $this->households->save($survivorHousehold);

        foreach ($survivorHousehold->releaseEvents() as $releasedEvent) {
            $this->eventBus->dispatch($releasedEvent, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
