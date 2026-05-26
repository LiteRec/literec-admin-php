<?php

declare(strict_types=1);

namespace App\Inventory\Domain\ValueObject;

use App\Inventory\Domain\Exception\InvalidComboComponent;

/**
 * One line in a Combo: an InventoryItem reference and how many units
 * of that item are decremented per combo sold.
 */
final readonly class ComboComponent
{
    public function __construct(
        public InventoryItemId $componentItemId,
        public Quantity $quantityPerCombo,
    ) {
        if ($quantityPerCombo->isZero()) {
            throw InvalidComboComponent::zeroQuantity();
        }
    }

    public function equals(self $other): bool
    {
        return $this->componentItemId->equals($other->componentItemId)
            && $this->quantityPerCombo->equals($other->quantityPerCombo);
    }
}
