<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use DomainException;

final class InventoryItemNotFound extends DomainException implements InventoryDomainException
{
    public static function withId(InventoryItemId $id): self
    {
        return new self(sprintf('Inventory item %s was not found.', $id->value));
    }

    public static function forListing(ListingId $listingId): self
    {
        return new self(sprintf(
            'No inventory item is registered for catalog listing %s.',
            $listingId->value,
        ));
    }
}
