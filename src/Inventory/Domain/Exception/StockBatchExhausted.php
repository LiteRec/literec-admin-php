<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\ValueObject\StockBatchId;
use DomainException;

final class StockBatchExhausted extends DomainException implements InventoryDomainException
{
    public static function for(StockBatchId $id): self
    {
        return new self(sprintf(
            'Stock batch %s has no remaining units.',
            $id->value,
        ));
    }
}
