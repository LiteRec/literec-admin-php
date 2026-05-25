<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use DomainException;

final class VendorNotFound extends DomainException implements InventoryDomainException
{
    public static function byId(string $value): self
    {
        return new self(sprintf('No Inventory Vendor with id "%s".', $value));
    }

    public static function byCode(string $value): self
    {
        return new self(sprintf('No Inventory Vendor with code "%s".', $value));
    }
}
