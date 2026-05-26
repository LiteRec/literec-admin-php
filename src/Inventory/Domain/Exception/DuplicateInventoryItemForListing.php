<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Catalog\Domain\ValueObject\ListingId;
use DomainException;

final class DuplicateInventoryItemForListing extends DomainException implements InventoryDomainException
{
    public static function for(ListingId $listingId): self
    {
        return new self(sprintf(
            'An inventory item is already registered for catalog listing %s.',
            $listingId->value,
        ));
    }
}
