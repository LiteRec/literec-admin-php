<?php

declare(strict_types=1);

namespace App\Households\Domain\Event;

use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\HouseholdName;
use DateTimeImmutable;

final readonly class HouseholdRegistered
{
    public function __construct(
        public HouseholdId $householdId,
        public HouseholdName $name,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
