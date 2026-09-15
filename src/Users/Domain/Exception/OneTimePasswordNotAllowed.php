<?php

declare(strict_types=1);

namespace App\Users\Domain\Exception;

use DomainException;

final class OneTimePasswordNotAllowed extends DomainException implements UsersDomainException
{
    public static function forInactiveUser(string $id): self
    {
        return new self(sprintf('Cannot issue a one-time password for inactive user "%s".', $id));
    }
}
