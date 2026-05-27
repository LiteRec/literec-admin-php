<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Inventory\Domain\ValueObject\InventoryItemId;
use DateTimeImmutable;

/**
 * Child entity owned by {@see ItemGroup} (LRA-96). Persistence shape
 * only — the public ItemGroup surface still exposes plain
 * {@see InventoryItemId} lists for events and external consumers.
 *
 * Identity: (group_id, item_id). The added_at timestamp powers the
 * LRA-97 "recent groups for this item" read path which orders
 * membership history newest-first per item.
 *
 * Although the constructor is `public` (PHP has no package-private
 * modifier), callers in Application or Infrastructure layers must
 * drive membership lifecycle through {@see ItemGroup} methods.
 */
final class ItemGroupMembership
{
    private ItemGroup $group;
    private InventoryItemId $itemId;
    private DateTimeImmutable $addedAt;

    public function __construct(
        ItemGroup $group,
        InventoryItemId $itemId,
        DateTimeImmutable $addedAt,
    ) {
        $this->group = $group;
        $this->itemId = $itemId;
        $this->addedAt = $addedAt;
    }

    public function group(): ItemGroup
    {
        return $this->group;
    }

    public function itemId(): InventoryItemId
    {
        return $this->itemId;
    }

    public function addedAt(): DateTimeImmutable
    {
        return $this->addedAt;
    }
}
