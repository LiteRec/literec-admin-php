<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\ValueObject\ComboId;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use DomainException;

final class ComboCycleDetected extends DomainException implements InventoryDomainException
{
    public static function at(ComboId $comboId, InventoryItemId $offendingItemId): self
    {
        return new self(sprintf(
            'Combo %s would form a cycle through inventory item %s.',
            $comboId->value,
            $offendingItemId->value,
        ));
    }
}
