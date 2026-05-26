<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\ValueObject\ItemGroupName;
use DomainException;

final class InvalidItemGroupName extends DomainException implements InventoryDomainException
{
    public static function empty(): self
    {
        return new self('Item group name cannot be empty after trimming whitespace.');
    }

    public static function tooLong(int $length): self
    {
        return new self(sprintf(
            'Item group name is %d characters; maximum is %d.',
            $length,
            ItemGroupName::MAX_LENGTH,
        ));
    }
}
