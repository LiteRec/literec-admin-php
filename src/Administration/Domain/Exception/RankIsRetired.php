<?php

declare(strict_types=1);

namespace App\Administration\Domain\Exception;

use App\Administration\Domain\ValueObject\RankId;
use DomainException;

final class RankIsRetired extends DomainException implements AdministrationDomainException
{
    public static function for(RankId $id): self
    {
        return new self(sprintf('Rank %s is retired.', $id->value));
    }
}
