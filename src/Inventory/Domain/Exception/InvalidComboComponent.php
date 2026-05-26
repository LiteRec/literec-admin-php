<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use DomainException;

final class InvalidComboComponent extends DomainException implements InventoryDomainException
{
    public static function zeroQuantity(): self
    {
        return new self('Combo component quantity-per-combo must be greater than zero.');
    }
}
