<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\InMemory;

use App\Inventory\Domain\Exception\DuplicateItemGroupName;
use App\Inventory\Domain\Exception\ItemGroupNotFound;
use App\Inventory\Domain\ItemGroup;
use App\Inventory\Domain\ItemGroups;
use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\ItemGroupId;
use App\Inventory\Domain\ValueObject\ItemGroupName;
use LogicException;

final class InMemoryItemGroups implements ItemGroups
{
    /** @var array<string, ItemGroup> */
    private array $byId = [];

    public function add(ItemGroup $group): void
    {
        if (isset($this->byId[$group->id()->value])) {
            throw new LogicException(sprintf(
                'Item group %s already exists; use save() to update.',
                $group->id()->value,
            ));
        }

        foreach ($this->byId as $existing) {
            if ($existing->name()->equals($group->name())) {
                throw DuplicateItemGroupName::for($group->name()->value);
            }
        }

        $this->byId[$group->id()->value] = $group;
    }

    public function save(ItemGroup $group): void
    {
        if (! isset($this->byId[$group->id()->value])) {
            throw ItemGroupNotFound::withId($group->id());
        }

        foreach ($this->byId as $existing) {
            if (
                ! $existing->id()->equals($group->id())
                && $existing->name()->equals($group->name())
            ) {
                throw DuplicateItemGroupName::for($group->name()->value);
            }
        }

        $this->byId[$group->id()->value] = $group;
    }

    public function byId(ItemGroupId $id): ItemGroup
    {
        if (! isset($this->byId[$id->value])) {
            throw ItemGroupNotFound::withId($id);
        }

        return $this->byId[$id->value];
    }

    public function byName(ItemGroupName $name): ItemGroup
    {
        foreach ($this->byId as $group) {
            if ($group->name()->equals($name)) {
                return $group;
            }
        }

        throw ItemGroupNotFound::withName($name);
    }

    public function forItem(InventoryItemId $itemId): array
    {
        $result = [];
        foreach ($this->byId as $group) {
            if ($group->hasMember($itemId)) {
                $result[] = $group;
            }
        }
        return $result;
    }

    public function availableAtFacility(FacilityCode $facility): array
    {
        $result = [];
        foreach ($this->byId as $group) {
            if ($group->facilityScope()->includes($facility)) {
                $result[] = $group;
            }
        }
        return $result;
    }
}
