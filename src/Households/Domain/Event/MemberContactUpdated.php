<?php

declare(strict_types=1);

namespace App\Households\Domain\Event;

use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;
use App\Shared\Domain\ValueObject\EmailAddress;
use App\Shared\Domain\ValueObject\PhoneNumber;
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
