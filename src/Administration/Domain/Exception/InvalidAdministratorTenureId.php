<?php

declare(strict_types=1);

namespace App\Administration\Domain\Exception;

use DomainException;

final class InvalidAdministratorTenureId extends DomainException implements AdministrationDomainException
{
    public static function for(string $value): self
    {
        return new self(sprintf('"%s" is not a valid UUID v7.', $value));
    }
}
