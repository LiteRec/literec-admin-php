<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\Quantity;
use DomainException;

/**
 * Thrown when a returnUnits() call asks to restore more than the
 * total quantity ever consumed from the item.
 */
final class InvalidStockReturn extends DomainException implements InventoryDomainException
{
    public readonly InventoryItemId $inventoryItemId;
    public readonly Quantity $requested;
    public readonly Quantity $restorable;

    private function __construct(
        InventoryItemId $inventoryItemId,
        Quantity $requested,
        Quantity $restorable,
    ) {
        parent::__construct(sprintf(
            'Inventory item %s can restore %d units; %d were requested for return.',
            $inventoryItemId->value,
            $restorable->units,
            $requested->units,
        ));

        $this->inventoryItemId = $inventoryItemId;
        $this->requested = $requested;
        $this->restorable = $restorable;
    }

    public static function for(
        InventoryItemId $inventoryItemId,
        Quantity $requested,
        Quantity $restorable,
    ): self {
        return new self($inventoryItemId, $requested, $restorable);
    }
}
