<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use App\Inventory\Domain\ValueObject\PurchaseOrderLineId;
use DomainException;

final class PurchaseOrderLineNotFound extends DomainException implements PurchaseOrderException
{
    public static function for(PurchaseOrderId $orderId, PurchaseOrderLineId $lineId): self
    {
        return new self(sprintf(
            'Line %s was not found on purchase order %s.',
            $lineId->value,
            $orderId->value,
        ));
    }
}
