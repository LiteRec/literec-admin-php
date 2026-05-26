<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Inventory\Domain\ValueObject\InventoryItemId;

/**
 * Domain port that answers two questions the {@see Combo} aggregate
 * needs at validation time:
 *
 * 1. Is this inventory item itself a combo? (enforces the no-nesting
 *    rule — combos may only contain atomic items.)
 * 2. Which inventory items does this item expand into? (used for cycle
 *    detection — Combo A's BFS rejects any reachable id already in its
 *    component set. Forward graph direction: parent → children.)
 *
 * Implementations live in Infrastructure and are typically backed by
 * the inventory_combo_components table; an InMemory adapter exists
 * for #[Small] unit tests.
 */
interface ComboGraphResolver
{
    public function isCombo(InventoryItemId $itemId): bool;

    /**
     * @return list<InventoryItemId> The inventory items that the given
     *         combo (referenced by its listing's mirror item id) expands
     *         into. Used by the BFS cycle walk. Returns [] for atomic
     *         items.
     */
    public function componentsOf(InventoryItemId $itemId): array;
}
