<?php

declare(strict_types=1);

namespace App\Administration\Domain\Exception;

use App\Administration\Domain\ValueObject\AdministratorTenureId;
use DomainException;

final class TenureAlreadyClosed extends DomainException implements AdministrationDomainException
{
    public static function for(AdministratorTenureId $id): self
    {
        return new self(sprintf('Tenure %s is already closed.', $id->value));
    }
}
