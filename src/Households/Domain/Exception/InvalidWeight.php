<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use DomainException;

final class InvalidWeight extends DomainException implements HouseholdsDomainException
{
    public const int MAX_POUNDS = 1500;

    public static function notPositive(): self
    {
        return new self('Weight must be a whole number of pounds greater than zero.');
    }

    public static function exceedsMaximum(): self
    {
        return new self(sprintf(
            'Weight must not exceed %d pounds.',
            self::MAX_POUNDS,
        ));
    }
}
