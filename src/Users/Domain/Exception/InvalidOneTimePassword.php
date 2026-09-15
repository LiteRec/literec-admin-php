<?php

declare(strict_types=1);

namespace App\Users\Domain\Exception;

use DomainException;

final class InvalidOneTimePassword extends DomainException implements UsersDomainException
{
    public static function length(int $actual, int $expected): self
    {
        return new self(sprintf(
            'A one-time password must be exactly %d characters long, got %d.',
            $expected,
            $actual,
        ));
    }

    public static function whitespace(): self
    {
        return new self('A one-time password must not contain whitespace.');
    }
}
