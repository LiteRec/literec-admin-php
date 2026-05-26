<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\ValueObject\InventoryItemId;
use DomainException;

final class InventoryItemIsArchived extends DomainException implements InventoryDomainException
{
    public static function for(InventoryItemId $id): self
    {
        return new self(sprintf(
            'Inventory item %s is archived and cannot be modified.',
            $id->value,
        ));
    }
}
