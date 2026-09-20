<?php

declare(strict_types=1);

namespace App\Administration\Domain\Exception;

use App\Administration\Domain\ValueObject\AdministratorId;
use DomainException;

final class AdministratorAlreadyActive extends DomainException implements AdministrationDomainException
{
    public static function for(AdministratorId $id): self
    {
        return new self(sprintf('Administrator %s is already active.', $id->value));
    }
}
