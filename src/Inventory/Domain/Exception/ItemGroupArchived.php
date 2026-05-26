<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\ValueObject\ItemGroupId;
use DomainException;

final class ItemGroupArchived extends DomainException implements InventoryDomainException
{
    public static function for(ItemGroupId $id): self
    {
        return new self(sprintf(
            'Item group %s is archived; new members cannot be added.',
            $id->value,
        ));
    }
}
