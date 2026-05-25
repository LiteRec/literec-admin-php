<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use DomainException;

final class ListingNotFound extends DomainException implements CatalogDomainException
{
    public static function byId(string $value): self
    {
        return new self(sprintf('No Catalog Listing with id "%s".', $value));
    }

    public static function byCode(string $value): self
    {
        return new self(sprintf('No Catalog Listing with code "%s".', $value));
    }
}
