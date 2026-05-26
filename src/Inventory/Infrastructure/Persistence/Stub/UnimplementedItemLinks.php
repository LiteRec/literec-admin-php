<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\Stub;

use App\Inventory\Domain\ItemLink;
use App\Inventory\Domain\ItemLinks;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\ItemLinkId;
use DateTimeImmutable;
use LogicException;

final class UnimplementedItemLinks implements ItemLinks
{
    public function add(ItemLink $link): void
    {
        throw self::notImplemented();
    }

    public function save(ItemLink $link): void
    {
        throw self::notImplemented();
    }

    public function remove(ItemLink $link): void
    {
        throw self::notImplemented();
    }

    public function byId(ItemLinkId $id): ItemLink
    {
        throw self::notImplemented();
    }

    public function byPair(InventoryItemId $masterItemId, InventoryItemId $linkedItemId): ItemLink
    {
        throw self::notImplemented();
    }

    public function existsForPair(InventoryItemId $masterItemId, InventoryItemId $linkedItemId): bool
    {
        throw self::notImplemented();
    }

    public function activeForMaster(InventoryItemId $masterItemId, DateTimeImmutable $now): array
    {
        throw self::notImplemented();
    }

    private static function notImplemented(): LogicException
    {
        return new LogicException(
            'Item link persistence is not yet implemented in production — '
            . 'the Doctrine adapter lands in the LRA-82 follow-up.',
        );
    }
}
