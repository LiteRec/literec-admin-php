<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use DomainException;

final class StockBatchAlreadyAttached extends DomainException implements InventoryDomainException
{
    public static function toAnotherItem(): self
    {
        return new self('StockBatch is already attached to a different InventoryItem.');
    }
}
