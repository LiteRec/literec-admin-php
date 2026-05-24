<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use DomainException;

final class InvalidMemberCode extends DomainException implements HouseholdsDomainException
{
    public const MAX_LENGTH = 32;

    public static function empty(): self
    {
        return new self('A member code must be a non-empty string.');
    }

    public static function tooLong(int $length): self
    {
        return new self(sprintf(
            'Member code length %d exceeds the maximum of %d characters.',
            $length,
            self::MAX_LENGTH,
        ));
    }

    public static function illegalCharacters(): self
    {
        return new self('Member code contains characters outside [A-Za-z0-9_-].');
    }
}
