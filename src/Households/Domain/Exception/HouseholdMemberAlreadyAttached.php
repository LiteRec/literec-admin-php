<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use DomainException;

final class HouseholdMemberAlreadyAttached extends DomainException implements HouseholdsDomainException
{
    public static function toAnotherHousehold(): self
    {
        return new self('HouseholdMember is already attached to a different Household.');
    }
}
