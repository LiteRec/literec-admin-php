<?php

declare(strict_types=1);

namespace App\Users\Domain\Exception;

use DomainException;

final class InvalidUsername extends DomainException implements UsersDomainException
{
    public const MAX_LENGTH = 180;

    public static function empty(): self
    {
        return new self('A user must have a non-empty username.');
    }

    public static function tooLong(int $length): self
    {
        return new self(sprintf(
            'Username length %d exceeds the maximum of %d characters.',
            $length,
            self::MAX_LENGTH,
        ));
    }
}
