<?php

declare(strict_types=1);

namespace App\Catalog\Domain\Exception;

use App\Catalog\Domain\ValueObject\ListingId;
use DomainException;

final class ListingIsArchived extends DomainException implements CatalogDomainException
{
    public static function for(ListingId $id): self
    {
        return new self(sprintf(
            'Listing %s is archived and cannot be modified.',
            $id->value,
        ));
    }
}
