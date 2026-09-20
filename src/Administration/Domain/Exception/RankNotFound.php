<?php

declare(strict_types=1);

namespace App\Administration\Domain\Exception;

use App\Administration\Domain\ValueObject\RankId;
use App\Administration\Domain\ValueObject\RankName;
use DomainException;

final class RankNotFound extends DomainException implements AdministrationDomainException
{
    public static function withId(RankId $id): self
    {
        return new self(sprintf('Rank %s was not found.', $id->value));
    }

    public static function withName(RankName $name): self
    {
        return new self(sprintf('Rank "%s" was not found.', $name->value));
    }
}
