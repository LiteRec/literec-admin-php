<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\ValueObject\VendorId;
use DomainException;

final class VendorIsArchived extends DomainException implements InventoryDomainException
{
    public static function for(VendorId $id): self
    {
        return new self(sprintf(
            'Vendor %s is archived and cannot be modified.',
            $id->value,
        ));
    }
}
