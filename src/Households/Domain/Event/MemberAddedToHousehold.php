<?php

declare(strict_types=1);

namespace App\Households\Domain\Event;

use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberCode;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\PersonName;
use DateTimeImmutable;

final readonly class MemberAddedToHousehold
{
    public function __construct(
        public HouseholdId $householdId,
        public MemberId $memberId,
        public MemberCode $memberCode,
        public PersonName $name,
        public bool $isPrimary,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
