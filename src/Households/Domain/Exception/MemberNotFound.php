<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use App\Households\Domain\ValueObject\HouseholdId;
use App\Households\Domain\ValueObject\MemberId;
use DomainException;

final class MemberNotFound extends DomainException implements HouseholdsDomainException
{
    public static function inHousehold(HouseholdId $householdId, MemberId $memberId): self
    {
        return new self(sprintf(
            'Household "%s" has no member with id "%s".',
            $householdId->value,
            $memberId->value,
        ));
    }
}
