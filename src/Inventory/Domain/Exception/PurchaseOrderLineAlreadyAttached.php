<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use DomainException;

final class PurchaseOrderLineAlreadyAttached extends DomainException implements PurchaseOrderException
{
    public static function toAnotherOrder(): self
    {
        return new self('PurchaseOrderLine is already attached to a different PurchaseOrder.');
    }
}
