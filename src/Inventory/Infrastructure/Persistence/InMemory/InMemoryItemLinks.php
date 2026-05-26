<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\InMemory;

use App\Inventory\Domain\Exception\DuplicateItemLink;
use App\Inventory\Domain\Exception\ItemLinkNotFound;
use App\Inventory\Domain\ItemLink;
use App\Inventory\Domain\ItemLinks;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\ItemLinkId;
use DateTimeImmutable;
use LogicException;

final class InMemoryItemLinks implements ItemLinks
{
    /** @var array<string, ItemLink> */
    private array $byId = [];

    public function add(ItemLink $link): void
    {
        if (isset($this->byId[$link->id()->value])) {
            throw new LogicException(sprintf(
                'Item link %s already exists; use save() to update.',
                $link->id()->value,
            ));
        }

        foreach ($this->byId as $existing) {
            if (
                $existing->masterItemId()->equals($link->masterItemId())
                && $existing->linkedItemId()->equals($link->linkedItemId())
            ) {
                throw DuplicateItemLink::forPair($link->masterItemId(), $link->linkedItemId());
            }
        }

        $this->byId[$link->id()->value] = $link;
    }

    public function save(ItemLink $link): void
    {
        if (! isset($this->byId[$link->id()->value])) {
            throw ItemLinkNotFound::withId($link->id());
        }
        $this->byId[$link->id()->value] = $link;
    }

    public function remove(ItemLink $link): void
    {
        unset($this->byId[$link->id()->value]);
    }

    public function byId(ItemLinkId $id): ItemLink
    {
        if (! isset($this->byId[$id->value])) {
            throw ItemLinkNotFound::withId($id);
        }
        return $this->byId[$id->value];
    }

    public function byPair(InventoryItemId $masterItemId, InventoryItemId $linkedItemId): ItemLink
    {
        foreach ($this->byId as $link) {
            if (
                $link->masterItemId()->equals($masterItemId)
                && $link->linkedItemId()->equals($linkedItemId)
            ) {
                return $link;
            }
        }
        throw ItemLinkNotFound::forPair($masterItemId, $linkedItemId);
    }

    public function existsForPair(InventoryItemId $masterItemId, InventoryItemId $linkedItemId): bool
    {
        foreach ($this->byId as $link) {
            if (
                $link->masterItemId()->equals($masterItemId)
                && $link->linkedItemId()->equals($linkedItemId)
            ) {
                return true;
            }
        }
        return false;
    }

    public function activeForMaster(InventoryItemId $masterItemId, DateTimeImmutable $now): array
    {
        $result = [];
        foreach ($this->byId as $link) {
            if (! $link->masterItemId()->equals($masterItemId)) {
                continue;
            }
            $until = $link->includeUntil();
            if ($until !== null && $now > $until) {
                continue;
            }
            $result[] = $link;
        }
        return $result;
    }
}
