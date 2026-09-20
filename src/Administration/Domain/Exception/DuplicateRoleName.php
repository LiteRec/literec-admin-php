<?php

declare(strict_types=1);

namespace App\Administration\Domain\Exception;

use DomainException;

final class DuplicateRoleName extends DomainException implements AdministrationDomainException
{
    public static function of(string $name): self
    {
        return new self(sprintf('A role named "%s" already exists.', $name));
    }
}
