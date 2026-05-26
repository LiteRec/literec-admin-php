<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use DomainException;

final class InvalidPosColor extends DomainException implements InventoryDomainException
{
    public static function for(string $value): self
    {
        return new self(sprintf(
            '"%s" is not a valid POS color. Expected 7-character #RRGGBB hex.',
            $value,
        ));
    }
}
