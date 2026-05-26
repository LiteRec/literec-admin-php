<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\PurchaseOrderStatus;
use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use DomainException;

final class PurchaseOrderNotSent extends DomainException implements PurchaseOrderException
{
    public static function for(PurchaseOrderId $id, PurchaseOrderStatus $actual): self
    {
        return new self(sprintf(
            'Purchase order %s must be Sent or PartiallyReceived to receive lines; current status is %s.',
            $id->value,
            $actual->value,
        ));
    }
}
