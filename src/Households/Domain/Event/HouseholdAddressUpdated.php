<?php

declare(strict_types=1);

namespace App\Households\Domain\Event;

use App\Households\Domain\ValueObject\Address;
use App\Households\Domain\ValueObject\HouseholdId;
use DateTimeImmutable;

/**
 * Address is owned by the household (all members share it), so this event
 * does not carry a MemberId.
 */
final readonly class HouseholdAddressUpdated
{
    public function __construct(
        public HouseholdId $householdId,
        public Address $newAddress,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
