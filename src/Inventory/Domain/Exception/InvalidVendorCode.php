<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use DomainException;

final class InvalidVendorCode extends DomainException implements InventoryDomainException
{
    public static function for(string $value): self
    {
        return new self(
            sprintf(
                '"%s" is not a valid vendor code. Expected 1-32 chars from A-Z, 0-9, '
                . 'underscore, or hyphen, starting with A-Z or 0-9.',
                $value,
            ),
        );
    }
}
