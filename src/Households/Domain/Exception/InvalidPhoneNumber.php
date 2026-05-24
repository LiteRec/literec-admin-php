<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use DomainException;

final class InvalidPhoneNumber extends DomainException implements HouseholdsDomainException
{
    public const MAX_LENGTH = 32;

    public static function empty(): self
    {
        return new self('A phone number must not be empty.');
    }

    public static function tooLong(int $length): self
    {
        return new self(sprintf(
            'Phone number length %d exceeds the maximum of %d characters.',
            $length,
            self::MAX_LENGTH,
        ));
    }

    public static function illegalCharacters(): self
    {
        return new self(
            'Phone number contains characters other than digits and a leading "+".',
        );
    }
}
