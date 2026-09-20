<?php

declare(strict_types=1);

namespace App\Administration\Domain\Exception;

use App\Administration\Domain\ValueObject\RoleName;
use DomainException;

final class InvalidRoleName extends DomainException implements AdministrationDomainException
{
    public static function empty(): self
    {
        return new self('Role name must not be empty.');
    }

    public static function tooLong(int $length): self
    {
        return new self(sprintf(
            'Role name is %d characters; the maximum is %d.',
            $length,
            RoleName::MAX_LENGTH,
        ));
    }
}
