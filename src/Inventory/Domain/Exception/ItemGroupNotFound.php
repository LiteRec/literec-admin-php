<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Exception;

use App\Inventory\Domain\ValueObject\ItemGroupId;
use App\Inventory\Domain\ValueObject\ItemGroupName;
use DomainException;

final class ItemGroupNotFound extends DomainException implements InventoryDomainException
{
    public static function withId(ItemGroupId $id): self
    {
        return new self(sprintf('Item group %s was not found.', $id->value));
    }

    public static function withName(ItemGroupName $name): self
    {
        return new self(sprintf('Item group "%s" was not found.', $name->value));
    }
}
