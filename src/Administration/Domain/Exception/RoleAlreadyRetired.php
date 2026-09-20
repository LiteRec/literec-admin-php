<?php

declare(strict_types=1);

namespace App\Administration\Domain\Exception;

use App\Administration\Domain\ValueObject\RoleId;
use DomainException;

final class RoleAlreadyRetired extends DomainException implements AdministrationDomainException
{
    public static function for(RoleId $id): self
    {
        return new self(sprintf('Role %s is already retired.', $id->value));
    }
}
