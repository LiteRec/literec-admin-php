<?php

declare(strict_types=1);

namespace App\Users\Domain\Exception;

use DomainException;

final class PasswordNotSet extends DomainException implements UsersDomainException
{
    public static function throw(): self
    {
        return new self('A user cannot be persisted without a password.');
    }
}
