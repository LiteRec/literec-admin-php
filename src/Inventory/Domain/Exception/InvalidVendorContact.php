<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use DomainException;

final class InvalidVendorContact extends DomainException implements InventoryDomainException
{
    public const MAX_LENGTH = 100;

    public static function empty(): self
    {
        return new self('A vendor contact must not be empty.');
    }

    public static function tooLong(int $length): self
    {
        return new self(sprintf(
            'Vendor contact length %d exceeds the maximum of %d characters.',
            $length,
            self::MAX_LENGTH,
        ));
    }
}
