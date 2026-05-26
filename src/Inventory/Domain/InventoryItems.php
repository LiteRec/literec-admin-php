<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Domain\Exception\DuplicateInventoryItemForListing;
use App\Inventory\Domain\Exception\InventoryItemNotFound;
use App\Inventory\Domain\ValueObject\InventoryItemId;

/**
 * Domain port for persisting and retrieving InventoryItem aggregates.
 *
 * Forbids generic finders (Doctrine `findBy`, `findOneBy`,
 * `createQueryBuilder` etc.); every accessor is named after a domain
 * question staff/admin users actually ask of the Inventory context.
 *
 * Read-side projections — searchByName(), lowStockAt(), the per-facility
 * movement history feed — live on dedicated read-model ports introduced
 * by LRA-84; this aggregate port stays focused on the write side
 * (load aggregate by identity, persist, enforce the
 * one-inventory-item-per-listing invariant).
 */
interface InventoryItems
{
    /**
     * Persists a newly registered inventory item.
     *
     * @throws DuplicateInventoryItemForListing when an item is already
     *         registered for the same Catalog listing. Production
     *         adapters rely on the database's unique constraint so
     *         concurrent inserts cannot race past the in-process check.
     */
    public function add(InventoryItem $item): void;

    /**
     * Persists modifications to an existing inventory item.
     *
     * @throws InventoryItemNotFound when no item with the given id has
     *         been persisted yet. Use {@see add()} to register new items.
     */
    public function save(InventoryItem $item): void;

    /**
     * @throws InventoryItemNotFound
     */
    public function byId(InventoryItemId $id): InventoryItem;

    /**
     * @throws InventoryItemNotFound
     */
    public function byListingId(ListingId $listingId): InventoryItem;

    public function existsForListing(ListingId $listingId): bool;
}
