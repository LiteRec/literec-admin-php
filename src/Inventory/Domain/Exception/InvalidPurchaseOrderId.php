<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use DomainException;

final class InvalidPurchaseOrderId extends DomainException implements PurchaseOrderException
{
    public static function for(string $value): self
    {
        return new self(sprintf('"%s" is not a valid UUID v7.', $value));
    }
}
