<?php

declare(strict_types=1);

namespace App\Households\Domain\Event;

use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\TransactionReferences;
use DateTimeImmutable;

/**
 * Recorded on the source member's owning {@see App\Households\Domain\Household}
 * aggregate when {@see Household::splitMember()} creates a new member and
 * assigns it the selected transaction references (LRA-209).
 */
final readonly class MemberSplitOff
{
    public function __construct(
        public HouseholdId $householdId,
        public MemberId $sourceMemberId,
        public MemberId $newMemberId,
        public MemberCode $newMemberCode,
        public TransactionReferences $transactions,
        public ?string $reason,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
