<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\InMemory;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Domain\Combo;
use App\Inventory\Domain\Combos;
use App\Inventory\Domain\Exception\ComboNotFound;
use App\Inventory\Domain\ValueObject\ComboId;
use App\Inventory\Domain\ValueObject\InventoryItemId;

/**
 * Array-backed adapter for the {@see Combos} port. Used by unit tests
 * and the Domain/Application layers so they can exercise combo flows
 * without a live database.
 */
final class InMemoryCombos implements Combos
{
    /** @var array<string, Combo> keyed by combo id string */
    private array $byId = [];

    public function add(Combo $combo): void
    {
        $this->byId[$combo->id()->value] = $combo;
    }

    public function save(Combo $combo): void
    {
        if (! isset($this->byId[$combo->id()->value])) {
            throw ComboNotFound::withId($combo->id());
        }

        $this->byId[$combo->id()->value] = $combo;
    }

    public function byId(ComboId $id): Combo
    {
        if (! isset($this->byId[$id->value])) {
            throw ComboNotFound::withId($id);
        }

        return $this->byId[$id->value];
    }

    public function byListingId(ListingId $listingId): Combo
    {
        foreach ($this->byId as $combo) {
            if ($combo->listingId()->equals($listingId)) {
                return $combo;
            }
        }

        throw ComboNotFound::forListing($listingId);
    }

    public function forComponent(InventoryItemId $componentItemId): array
    {
        $result = [];
        foreach ($this->byId as $combo) {
            foreach ($combo->components() as $component) {
                if ($component->componentItemId->equals($componentItemId)) {
                    $result[] = $combo;
                    break;
                }
            }
        }

        return $result;
    }
}
