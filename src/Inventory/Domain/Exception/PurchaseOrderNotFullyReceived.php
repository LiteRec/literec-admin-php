<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use DomainException;

final class PurchaseOrderNotFullyReceived extends DomainException implements PurchaseOrderException
{
    public static function for(PurchaseOrderId $id): self
    {
        return new self(sprintf(
            'Purchase order %s cannot be verified until every line is fully received.',
            $id->value,
        ));
    }
}
