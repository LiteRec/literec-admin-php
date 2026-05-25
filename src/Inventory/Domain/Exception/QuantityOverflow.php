<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use DomainException;

final class QuantityOverflow extends DomainException implements InventoryDomainException
{
    public static function adding(int $left, int $right): self
    {
        return new self(
            sprintf('Adding %d to %d units would overflow PHP_INT_MAX.', $right, $left),
        );
    }

    public static function multiplying(int $costCents, int $units): self
    {
        return new self(
            sprintf(
                'Multiplying %d cents by %d units would overflow PHP_INT_MAX.',
                $costCents,
                $units,
            ),
        );
    }
}
