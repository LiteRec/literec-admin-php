<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\Quantity;
use DomainException;

/**
 * Thrown when an InventoryItem cannot satisfy a consume request.
 *
 * Carries both the requested and the available quantity so the
 * application layer / ACL (LRA-83) can publish a useful
 * {@see App\Inventory\Integration\Event\StockConsumptionFailed}
 * integration event without reparsing the message string.
 */
final class InsufficientStock extends DomainException implements InventoryDomainException
{
    public readonly InventoryItemId $inventoryItemId;
    public readonly Quantity $requested;
    public readonly Quantity $available;

    private function __construct(
        InventoryItemId $inventoryItemId,
        Quantity $requested,
        Quantity $available,
    ) {
        parent::__construct(sprintf(
            'Inventory item %s has %d units available; %d were requested.',
            $inventoryItemId->value,
            $available->units,
            $requested->units,
        ));

        $this->inventoryItemId = $inventoryItemId;
        $this->requested = $requested;
        $this->available = $available;
    }

    public static function for(
        InventoryItemId $inventoryItemId,
        Quantity $requested,
        Quantity $available,
    ): self {
        return new self($inventoryItemId, $requested, $available);
    }
}
