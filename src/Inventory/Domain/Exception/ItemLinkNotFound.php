<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\ItemLinkId;
use DomainException;

final class ItemLinkNotFound extends DomainException implements InventoryDomainException
{
    public static function withId(ItemLinkId $id): self
    {
        return new self(sprintf('Item link %s was not found.', $id->value));
    }

    public static function forPair(InventoryItemId $masterItemId, InventoryItemId $linkedItemId): self
    {
        return new self(sprintf(
            'No item link found for pair %s → %s.',
            $masterItemId->value,
            $linkedItemId->value,
        ));
    }
}
