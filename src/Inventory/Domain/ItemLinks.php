<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Inventory\Domain\Exception\ItemLinkNotFound;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\ItemLinkId;
use DateTimeImmutable;

interface ItemLinks
{
    public function add(ItemLink $link): void;

    /**
     * @throws ItemLinkNotFound
     */
    public function save(ItemLink $link): void;

    public function remove(ItemLink $link): void;

    /**
     * @throws ItemLinkNotFound
     */
    public function byId(ItemLinkId $id): ItemLink;

    /**
     * @throws ItemLinkNotFound
     */
    public function byPair(InventoryItemId $masterItemId, InventoryItemId $linkedItemId): ItemLink;

    public function existsForPair(InventoryItemId $masterItemId, InventoryItemId $linkedItemId): bool;

    /**
     * Returns active links for the master item — excludes any link whose
     * includeUntil has already passed at the supplied $now.
     *
     * @return list<ItemLink>
     */
    public function activeForMaster(InventoryItemId $masterItemId, DateTimeImmutable $now): array;
}
