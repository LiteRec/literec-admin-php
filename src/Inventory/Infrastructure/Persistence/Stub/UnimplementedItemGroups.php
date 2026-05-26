<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Stub;

use App\Inventory\Domain\ItemGroup;
use App\Inventory\Domain\ItemGroups;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\ItemGroupId;
use App\Inventory\Domain\ValueObject\ItemGroupName;
use LogicException;

/**
 * Fail-loud production binding for {@see ItemGroups} while the Doctrine
 * adapter + migration are in flight. Every method throws so the
 * container compiles cleanly but accidental dispatch in production
 * surfaces immediately instead of writing to non-durable storage.
 */
final class UnimplementedItemGroups implements ItemGroups
{
    public function add(ItemGroup $group): void
    {
        throw self::notImplemented();
    }

    public function save(ItemGroup $group): void
    {
        throw self::notImplemented();
    }

    public function byId(ItemGroupId $id): ItemGroup
    {
        throw self::notImplemented();
    }

    public function byName(ItemGroupName $name): ItemGroup
    {
        throw self::notImplemented();
    }

    public function forItem(InventoryItemId $itemId): array
    {
        throw self::notImplemented();
    }

    public function availableAtFacility(FacilityCode $facility): array
    {
        throw self::notImplemented();
    }

    private static function notImplemented(): LogicException
    {
        return new LogicException(
            'Item group persistence is not yet implemented in production — '
            . 'the Doctrine adapter lands in the LRA-81 follow-up. Dispatching '
            . 'an item-group command on this binding is a bug.',
        );
    }
}
