<?php

declare(strict_types=1);

namespace App\Administration\Domain\Exception;

use App\Administration\Domain\ValueObject\RoleDescription;
use DomainException;

final class InvalidRoleDescription extends DomainException implements AdministrationDomainException
{
    public static function tooLong(int $length): self
    {
        return new self(sprintf(
            'Role description is %d characters; the maximum is %d.',
            $length,
            RoleDescription::MAX_LENGTH,
        ));
    }
}
