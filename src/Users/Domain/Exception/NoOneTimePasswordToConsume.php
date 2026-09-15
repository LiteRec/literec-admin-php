<?php

declare(strict_types=1);

namespace App\Users\Domain\Exception;

use DomainException;

final class NoOneTimePasswordToConsume extends DomainException implements UsersDomainException
{
    public static function for(string $id): self
    {
        return new self(sprintf('User "%s" has no issued one-time password to consume.', $id));
    }
}
