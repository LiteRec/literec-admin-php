<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use DomainException;

final class ComboRequiresComponents extends DomainException implements InventoryDomainException
{
    public static function empty(): self
    {
        return new self('A combo must contain at least one component.');
    }
}
