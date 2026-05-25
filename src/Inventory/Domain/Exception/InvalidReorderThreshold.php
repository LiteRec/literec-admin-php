<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use DomainException;

final class InvalidReorderThreshold extends DomainException implements InventoryDomainException
{
    public static function for(int $units): self
    {
        return new self(
            sprintf('Reorder threshold must be zero or positive; got %d units.', $units),
        );
    }
}
