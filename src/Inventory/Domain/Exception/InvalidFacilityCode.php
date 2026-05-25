<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use DomainException;

final class InvalidFacilityCode extends DomainException implements InventoryDomainException
{
    public static function for(string $value): self
    {
        return new self(
            sprintf(
                '"%s" is not a valid facility code. Expected 2-16 chars starting with A-Z, '
                . 'remaining characters from A-Z, 0-9, underscore, or hyphen.',
                $value,
            ),
        );
    }
}
