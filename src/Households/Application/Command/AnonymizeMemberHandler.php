<?php

declare(strict_types=1);

namespace App\Households\Application\Command;

use App\Households\Domain\Households;
use App\Households\Domain\MemberFreeTextReasons;
use App\Households\Domain\ValueObject\AnonymizedProfile;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;
use Psr\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentBusStamp;

#[AsMessageHandler(bus: 'command.bus')]
final class AnonymizeMemberHandler
{
    public function __construct(
        private readonly Households $households,
        private readonly MemberFreeTextReasons $freeTextReasons,
        private readonly ClockInterface $clock,
        private readonly MessageBusInterface $eventBus,
    ) {
    }

    public function __invoke(AnonymizeMember $command): void
    {
        $household = $this->households->findById(HouseholdId::fromString($command->householdId));
        $memberId = MemberId::fromString($command->memberId);

        $household->anonymizeMember($memberId, AnonymizedProfile::placeholder(), $this->clock);
        $this->households->save($household);
        // Scrubbed inside this same command.bus doctrine_transaction, not
        // from a post-commit event handler (LRA-212): a mid-scrub failure
        // must roll back the member's anonymized_at write too, rather than
        // leave an unretryable record with its residency/lineage free text
        // still intact — a second anonymize attempt is refused by
        // MemberIsAnonymized::cannotBeAnonymizedAgain().
        $this->freeTextReasons->scrubFor($memberId);

        foreach ($household->releaseEvents() as $event) {
            $this->eventBus->dispatch($event, [new DispatchAfterCurrentBusStamp()]);
        }
    }
}
