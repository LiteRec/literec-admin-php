<?php

declare(strict_types=1);

namespace App\Users\Domain\Exception;

use DomainException;

final class InvalidUserId extends DomainException implements UsersDomainException
{
    public static function for(string $value): self
    {
        return new self(sprintf('"%s" is not a valid UUID v7.', $value));
    }
}
