<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use DomainException;

final class InvalidStockAdjustmentSubReason extends DomainException implements InventoryDomainException
{
    public static function for(string $raw): self
    {
        return new self(sprintf(
            'Unknown StockAdjustmentReason value: %s.',
            $raw === '' ? '(empty)' : $raw,
        ));
    }
}
