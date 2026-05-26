<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Catalog\Domain\ValueObject\ListingId;
use App\Inventory\Domain\Exception\ComboNotFound;
use App\Inventory\Domain\ValueObject\ComboId;
use App\Inventory\Domain\ValueObject\InventoryItemId;

/**
 * Domain port for persisting and retrieving Combo aggregates.
 *
 * Named finders only — no generic findBy / findOneBy / createQueryBuilder.
 * The {@see forComponent()} finder backs the post-commit listener that
 * flags every owning combo as invalid when one of its components is
 * archived (the listener itself lands with the LRA-83 ACL).
 */
interface Combos
{
    public function add(Combo $combo): void;

    /**
     * @throws ComboNotFound when no combo with the given id has been
     *         persisted yet. Use {@see add()} to register new combos.
     */
    public function save(Combo $combo): void;

    /**
     * @throws ComboNotFound
     */
    public function byId(ComboId $id): Combo;

    /**
     * @throws ComboNotFound
     */
    public function byListingId(ListingId $listingId): Combo;

    /**
     * @return list<Combo>
     */
    public function forComponent(InventoryItemId $componentItemId): array;
}
