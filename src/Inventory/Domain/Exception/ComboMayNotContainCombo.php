<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\ValueObject\InventoryItemId;
use DomainException;

final class ComboMayNotContainCombo extends DomainException implements InventoryDomainException
{
    public static function withComponent(InventoryItemId $componentItemId): self
    {
        return new self(sprintf(
            'Combo component %s is itself a combo; combos must reference atomic inventory items only.',
            $componentItemId->value,
        ));
    }
}
