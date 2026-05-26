<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Inventory\Domain\Exception\ItemGroupNotFound;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\ItemGroupId;
use App\Inventory\Domain\ValueObject\ItemGroupName;

/**
 * Domain port for persisting and retrieving ItemGroup aggregates.
 *
 * Named finders only. Domain code never sees Doctrine; the read-model
 * port introduced in LRA-84 owns list/projection queries.
 */
interface ItemGroups
{
    /**
     * Persists a newly created item group.
     *
     * Adapters MAY throw {@see App\Inventory\Domain\Exception\DuplicateItemGroupName}
     * when a group with the same {@see ItemGroupName} already exists; the
     * production unique-name constraint will surface the same error
     * from the database side.
     */
    public function add(ItemGroup $group): void;

    /**
     * Persists modifications to an existing item group.
     *
     * @throws ItemGroupNotFound when no group with the given id has been
     *         persisted yet; use {@see add()} to register new groups.
     */
    public function save(ItemGroup $group): void;

    /**
     * @throws ItemGroupNotFound
     */
    public function byId(ItemGroupId $id): ItemGroup;

    /**
     * @throws ItemGroupNotFound
     */
    public function byName(ItemGroupName $name): ItemGroup;

    /**
     * @return list<ItemGroup>
     */
    public function forItem(InventoryItemId $itemId): array;

    /**
     * Returns groups whose facility scope includes the supplied facility
     * (ALL-scope groups always match).
     *
     * @return list<ItemGroup>
     */
    public function availableAtFacility(FacilityCode $facility): array;
}
