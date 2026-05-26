<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\InMemory;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Domain\Exception\DuplicateInventoryItemForListing;
use App\Inventory\Domain\Exception\InventoryItemNotFound;
use App\Inventory\Domain\InventoryItem;
use App\Inventory\Domain\InventoryItems;
use App\Inventory\Domain\ValueObject\InventoryItemId;

/**
 * Array-backed adapter for the {@see InventoryItems} port. Used by unit
 * and contract tests so the Domain layer does not require a live
 * database.
 *
 * Maintains a secondary index from {@see ListingId} to item id to enforce
 * the one-inventory-item-per-listing invariant the production Postgres
 * unique constraint guards.
 */
final class InMemoryInventoryItems implements InventoryItems
{
    /** @var array<string, InventoryItem> keyed by inventory item id string */
    private array $byId = [];

    /** @var array<string, string> listing id string -> inventory item id string */
    private array $byListing = [];

    public function add(InventoryItem $item): void
    {
        $listingValue = $item->listingId()->value;

        if (
            isset($this->byListing[$listingValue])
            && $this->byListing[$listingValue] !== $item->id()->value
        ) {
            throw DuplicateInventoryItemForListing::for($item->listingId());
        }

        $this->byId[$item->id()->value] = $item;
        $this->byListing[$listingValue] = $item->id()->value;
    }

    /**
     * Persists subsequent updates to an aggregate already in the index.
     *
     * Contract: {@see InventoryItem::listingId()} is immutable — the
     * aggregate has no mutator that reassigns it. save() updates the
     * by-id slot in place and re-writes the by-listing entry to the
     * same key, never stripping a stale {@see $byListing} mapping.
     * Production adapters guard the same invariant via the unique
     * constraint on inventory_items.listing_id (LRA-76 migration).
     */
    public function save(InventoryItem $item): void
    {
        if (! isset($this->byId[$item->id()->value])) {
            throw InventoryItemNotFound::withId($item->id());
        }

        $this->byId[$item->id()->value] = $item;
        $this->byListing[$item->listingId()->value] = $item->id()->value;
    }

    public function byId(InventoryItemId $id): InventoryItem
    {
        if (! isset($this->byId[$id->value])) {
            throw InventoryItemNotFound::withId($id);
        }

        return $this->byId[$id->value];
    }

    public function byListingId(ListingId $listingId): InventoryItem
    {
        if (! isset($this->byListing[$listingId->value])) {
            throw InventoryItemNotFound::forListing($listingId);
        }

        return $this->byId[$this->byListing[$listingId->value]];
    }

    public function existsForListing(ListingId $listingId): bool
    {
        return isset($this->byListing[$listingId->value]);
    }
}
