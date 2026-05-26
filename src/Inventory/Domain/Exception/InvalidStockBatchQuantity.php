<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use DomainException;

final class InvalidStockBatchQuantity extends DomainException implements InventoryDomainException
{
    public static function mustBePositive(): self
    {
        return new self('Stock batch quantity must be greater than zero.');
    }
}
