<?php

declare(strict_types=1);

namespace App\Households\Domain\Event;

use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;
use DateTimeImmutable;

/**
 * Deliberately carries no name, reason, or other free text: anonymization
 * exists to remove PII, so the event that announces it must not smuggle any
 * back in (LRA-212). Published post-commit for out-of-process subscribers
 * only; scrubbing the member's residency/lineage free text is not one of
 * them — {@see \App\Households\Application\Command\AnonymizeMemberHandler}
 * calls {@see \App\Households\Domain\MemberFreeTextReasons} synchronously,
 * inside the same transaction as the aggregate write, so that scrub is
 * atomic with `anonymized_at` rather than depending on this event firing.
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
