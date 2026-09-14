<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use DomainException;

final class InvalidHeight extends DomainException implements HouseholdsDomainException
{
    public const int MAX_INCHES = 107;

    public static function notPositive(): self
    {
        return new self('Height must be a whole number of inches greater than zero.');
    }

    public static function exceedsMaximum(): self
    {
        return new self(sprintf(
            'Height must not exceed %d inches.',
            self::MAX_INCHES,
        ));
    }
}
