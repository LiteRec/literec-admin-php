<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use DomainException;

final class InvalidDateOfBirth extends DomainException implements HouseholdsDomainException
{
    public static function future(): self
    {
        return new self('A date of birth must not be in the future.');
    }

    public static function malformed(string $value): self
    {
        return new self(sprintf('"%s" is not a parseable date string.', $value));
    }
}
