<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use DomainException;

final class InvalidHouseholdName extends DomainException implements HouseholdsDomainException
{
    public const MAX_LENGTH = 200;

    public static function empty(): self
    {
        return new self('A household must have a non-empty name.');
    }

    public static function tooLong(int $length): self
    {
        return new self(sprintf(
            'Household name length %d exceeds the maximum of %d characters.',
            $length,
            self::MAX_LENGTH,
        ));
    }
}
