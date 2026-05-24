<?php

declare(strict_types=1);

namespace App\Households\Domain\Exception;

use DomainException;

final class InvalidEmailAddress extends DomainException implements HouseholdsDomainException
{
    public const MAX_LENGTH = 254;

    public static function empty(): self
    {
        return new self('An email address must not be empty.');
    }

    public static function tooLong(int $length): self
    {
        return new self(sprintf(
            'Email address length %d exceeds the maximum of %d characters.',
            $length,
            self::MAX_LENGTH,
        ));
    }

    public static function malformed(): self
    {
        return new self('The provided value is not a syntactically valid email address.');
    }
}
