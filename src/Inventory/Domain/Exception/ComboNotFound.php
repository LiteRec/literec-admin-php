<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Domain\ValueObject\ComboId;
use DomainException;

final class ComboNotFound extends DomainException implements InventoryDomainException
{
    public static function withId(ComboId $id): self
    {
        return new self(sprintf('Combo %s was not found.', $id->value));
    }

    public static function forListing(ListingId $listingId): self
    {
        return new self(sprintf(
            'No combo is registered for catalog listing %s.',
            $listingId->value,
        ));
    }
}
