<?php

declare(strict_types=1);

namespace App\Households\Domain\Event;

use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;
use DateTimeImmutable;

final readonly class MemberSharingWithdrawn
{
    public function __construct(
        public HouseholdId $householdId,
        public MemberId $memberId,
        public HouseholdId $sharedHouseholdId,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
