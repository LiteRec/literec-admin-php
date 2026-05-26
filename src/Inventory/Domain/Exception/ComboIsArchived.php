<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\ValueObject\ComboId;
use DomainException;

final class ComboIsArchived extends DomainException implements InventoryDomainException
{
    public static function for(ComboId $id): self
    {
        return new self(sprintf(
            'Combo %s is archived and cannot be modified.',
            $id->value,
        ));
    }
}
