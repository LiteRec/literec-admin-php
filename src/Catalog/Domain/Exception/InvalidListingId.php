<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use DomainException;

final class InvalidListingId extends DomainException implements CatalogDomainException
{
    public static function for(string $value): self
    {
        return new self(sprintf('"%s" is not a valid UUID v7.', $value));
    }
}
