<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use DomainException;

final class PurchaseOrderRequiresLines extends DomainException implements PurchaseOrderException
{
    public static function empty(): self
    {
        return new self('Purchase order must include at least one line.');
    }
}
