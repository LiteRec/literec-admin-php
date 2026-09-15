<?php

declare(strict_types=1);

namespace App\Households\Infrastructure\Acl;

use App\Households\Domain\Event\MemberSplitOff;
use App\Households\Integration\Event\MemberTransactionsSplitOff;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Translates the domain {@see MemberSplitOff} event into the published
 * {@see MemberTransactionsSplitOff} integration event and dispatches it
 * on the event bus (LRA-209), where messenger.yaml routes it to the
 * async transport for downstream contexts.
 */
#[AsMessageHandler(bus: 'event.bus')]
final class PublishMemberTransactionsSplitOffHandler
{
    public function __construct(private readonly MessageBusInterface $eventBus)
    {
    }

    public function __invoke(MemberSplitOff $event): void
    {
        $this->eventBus->dispatch(new MemberTransactionsSplitOff(
            householdId: $event->householdId->value,
            sourceMemberId: $event->sourceMemberId->value,
            newMemberId: $event->newMemberId->value,
            transactionIds: $event->transactions->toStrings(),
            occurredAt: $event->occurredAt,
        ));
    }
}
