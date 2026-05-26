<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use DomainException;

final class ComboDepthExceeded extends DomainException implements InventoryDomainException
{
    public static function atDepth(int $depth, int $maxDepth): self
    {
        return new self(sprintf(
            'Combo expansion exceeded the maximum depth of %d (reached %d).',
            $maxDepth,
            $depth,
        ));
    }
}
