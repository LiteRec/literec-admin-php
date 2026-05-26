<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\ValueObject\InventoryItemId;
use DomainException;

final class LinkToSelfForbidden extends DomainException implements InventoryDomainException
{
    public static function for(InventoryItemId $itemId): self
    {
        return new self(sprintf(
            'Inventory item %s cannot be linked to itself.',
            $itemId->value,
        ));
    }
}
