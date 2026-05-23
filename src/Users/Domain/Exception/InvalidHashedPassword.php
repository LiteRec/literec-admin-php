<?php

declare(strict_types=1);

namespace App\Users\Domain\Exception;

use DomainException;

final class InvalidHashedPassword extends DomainException implements UsersDomainException
{
    public static function empty(): self
    {
        return new self('A hashed password must not be empty.');
    }

    public static function format(): self
    {
        return new self('The provided string is not a recognized password hash.');
    }
}
