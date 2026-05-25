<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use DomainException;

final class DuplicateVendorCode extends DomainException implements InventoryDomainException
{
    public static function for(string $value): self
    {
        return new self(sprintf(
            'An Inventory Vendor with code "%s" already exists.',
            $value,
        ));
    }
}
