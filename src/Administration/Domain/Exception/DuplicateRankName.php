<?php

declare(strict_types=1);

namespace App\Administration\Domain\Exception;

use DomainException;

final class DuplicateRankName extends DomainException implements AdministrationDomainException
{
    public static function of(string $name): self
    {
        return new self(sprintf('A rank named "%s" already exists.', $name));
    }
}
