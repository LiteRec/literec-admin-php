<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use DomainException;

final class DuplicateItemGroupName extends DomainException implements InventoryDomainException
{
    public static function for(string $name): self
    {
        return new self(sprintf('Item group "%s" already exists.', $name));
    }
}
