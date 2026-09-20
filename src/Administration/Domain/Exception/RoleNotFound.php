<?php

declare(strict_types=1);

namespace App\Administration\Domain\Exception;

use App\Administration\Domain\ValueObject\RoleId;
use App\Administration\Domain\ValueObject\RoleName;
use DomainException;

final class RoleNotFound extends DomainException implements AdministrationDomainException
{
    public static function withId(RoleId $id): self
    {
        return new self(sprintf('Role %s was not found.', $id->value));
    }

    public static function withName(RoleName $name): self
    {
        return new self(sprintf('Role "%s" was not found.', $name->value));
    }
}
