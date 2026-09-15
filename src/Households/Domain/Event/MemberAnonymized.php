<?php

declare(strict_types=1);

namespace App\Households\Domain\Event;

use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;
use DateTimeImmutable;

/**
 * Deliberately carries no name, reason, or other free text: anonymization
 * exists to remove PII, so the event that announces it must not smuggle any
 * back in (LRA-212). Subscribers that need to react (e.g.
 * {@see \App\Households\Infrastructure\Persistence\Doctrine\Event\ScrubResidencyHistoryReasonsHandler})
 * have everything they need from the ids alone.
 */
final readonly class MemberAnonymized
{
    public function __construct(
        public HouseholdId $householdId,
        public MemberId $memberId,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
