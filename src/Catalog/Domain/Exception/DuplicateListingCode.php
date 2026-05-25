<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use DomainException;

final class DuplicateListingCode extends DomainException implements CatalogDomainException
{
    public static function for(string $value): self
    {
        return new self(sprintf(
            'A Catalog Listing with code "%s" already exists.',
            $value,
        ));
    }
}
