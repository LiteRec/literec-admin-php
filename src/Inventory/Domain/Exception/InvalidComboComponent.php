<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\ValueObject\InventoryItemId;
use DomainException;

final class InvalidComboComponent extends DomainException implements InventoryDomainException
{
    public static function zeroQuantity(): self
    {
        return new self('Combo component quantity-per-combo must be greater than zero.');
    }

    public static function duplicateComponent(InventoryItemId $itemId): self
    {
        return new self(sprintf(
            'Combo cannot list the same component item more than once: %s.',
            $itemId->value,
        ));
    }
}
