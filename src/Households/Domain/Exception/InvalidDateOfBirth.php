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

    /**
     * @param string $value Not embedded in the message — date-of-birth input
     *                      is PII even when malformed.
     */
    public static function malformed(string $value): self
    {
        unset($value);

        return new self('The provided value is not a parseable date string.');
    }
}
