<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\ValueObject\InventoryItemId;
use DomainException;

final class DuplicateItemLink extends DomainException implements InventoryDomainException
{
    public static function forPair(InventoryItemId $masterItemId, InventoryItemId $linkedItemId): self
    {
        return new self(sprintf(
            'Inventory items %s → %s are already linked.',
            $masterItemId->value,
            $linkedItemId->value,
        ));
    }
}
