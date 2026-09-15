<?php

declare(strict_types=1);

namespace App\Households\Domain\Event;

use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
use DateTimeImmutable;

/**
 * Recorded on the duplicate's owning {@see App\Households\Domain\Household}
 * aggregate when {@see Household::mergeMemberInto()} merges a duplicate
 * member into a survivor (LRA-208).
 *
 * $email / $phone carry the duplicate's contact fields at merge time
 * (precedent: {@see MemberContactUpdated}) so the post-commit gap-fill
 * subscriber ({@see App\Households\Application\Event\FillSurvivorContactFromMergedMemberHandler})
 * never has to reload the losing aggregate.
 */
final readonly class MemberMergedInto
{
    public function __construct(
        public HouseholdId $householdId,
        public MemberId $memberId,
        public HouseholdId $survivorHouseholdId,
        public MemberId $survivorMemberId,
        public ?EmailAddress $email,
        public ?PhoneNumber $phone,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
