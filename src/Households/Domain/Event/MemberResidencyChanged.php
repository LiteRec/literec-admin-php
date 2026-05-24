<?php

declare(strict_types=1);

namespace App\Households\Domain\Event;

use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\ResidencyStatus;
use DateTimeImmutable;

final readonly class MemberResidencyChanged
{
    public function __construct(
        public HouseholdId $householdId,
        public MemberId $memberId,
        public ResidencyStatus $status,
        public DateTimeImmutable $effectiveFrom,
        public DateTimeImmutable $occurredAt,
        public ?string $reason = null,
    ) {
    }
}
