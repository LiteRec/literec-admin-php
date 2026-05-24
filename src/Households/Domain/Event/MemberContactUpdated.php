<?php

declare(strict_types=1);

namespace App\Households\Domain\Event;

use App\Households\Domain\ValueObject\EmailAddress;
use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;
use App\Households\Domain\ValueObject\PhoneNumber;
use DateTimeImmutable;

final readonly class MemberContactUpdated
{
    public function __construct(
        public HouseholdId $householdId,
        public MemberId $memberId,
        public ?EmailAddress $email,
        public ?PhoneNumber $phone,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
