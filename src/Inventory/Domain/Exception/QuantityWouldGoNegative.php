<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use DomainException;

final class QuantityWouldGoNegative extends DomainException implements InventoryDomainException
{
    public static function subtracting(int $from, int $by): self
    {
        return new self(
            sprintf('Cannot subtract %d units from %d units: result would be negative.', $by, $from),
        );
    }

    public static function ofRawValue(int $units): self
    {
        return new self(
            sprintf('Quantity must be zero or positive; got %d units.', $units),
        );
    }
}
