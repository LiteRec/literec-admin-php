<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use DomainException;

final class InvalidHouseholdId extends DomainException implements HouseholdsDomainException
{
    public static function for(string $value): self
    {
        return new self(sprintf('"%s" is not a valid UUID v7.', $value));
    }
}
