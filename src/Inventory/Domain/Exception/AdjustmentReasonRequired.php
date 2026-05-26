<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use DomainException;

final class AdjustmentReasonRequired extends DomainException implements InventoryDomainException
{
    public static function empty(): self
    {
        return new self('Stock adjustment requires a non-empty operator reason.');
    }
}
