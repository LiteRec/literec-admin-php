<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Acl;

use App\Households\Domain\Event\MemberMergedInto;
use App\Households\Integration\Event\MemberMerged;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Translates the domain {@see MemberMergedInto} event into the published
 * {@see MemberMerged} integration event and dispatches it on the event
 * bus (LRA-208), where messenger.yaml routes it to the async transport
 * for downstream contexts.
 */
#[AsMessageHandler(bus: 'event.bus')]
final class PublishMemberMergedHandler
{
    public function __construct(private readonly MessageBusInterface $eventBus)
    {
    }

    public function __invoke(MemberMergedInto $event): void
    {
        $this->eventBus->dispatch(new MemberMerged(
            survivorHouseholdId: $event->survivorHouseholdId->value,
            survivorMemberId: $event->survivorMemberId->value,
            mergedHouseholdId: $event->householdId->value,
            mergedMemberId: $event->memberId->value,
            occurredAt: $event->occurredAt,
        ));
    }
}
