<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use DomainException;

final class PurchaseOrderNotFound extends DomainException implements PurchaseOrderException
{
    public static function withId(PurchaseOrderId $id): self
    {
        return new self(sprintf('Purchase order %s was not found.', $id->value));
    }
}
