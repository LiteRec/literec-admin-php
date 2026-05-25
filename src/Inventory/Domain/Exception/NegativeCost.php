<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use DomainException;

final class NegativeCost extends DomainException implements InventoryDomainException
{
    public static function ofCents(int $cents): self
    {
        return new self(sprintf('Cost amounts must be zero or positive; got %d cents.', $cents));
    }
}
