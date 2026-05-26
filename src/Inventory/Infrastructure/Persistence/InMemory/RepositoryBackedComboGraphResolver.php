<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Persistence\InMemory;

use App\Inventory\Domain\ComboGraphResolver;
use App\Inventory\Domain\Combos;
use App\Inventory\Domain\Exception\ComboNotFound;
use App\Inventory\Domain\Exception\InventoryItemNotFound;
use App\Inventory\Domain\InventoryItems;
use App\Inventory\Domain\ValueObject\InventoryItemId;

/**
 * Combo graph resolver backed by the existing {@see Combos} and
 * {@see InventoryItems} ports.
 *
 * For {@see isCombo()} the resolver loads the inventory item, reads its
 * listing id, and asks the {@see Combos} repository whether any combo
 * is registered for that listing. {@see componentsOf()} returns the
 * components of the matching combo, mapped back to InventoryItemIds.
 *
 * Used as the production wiring for the Combo aggregate's validation
 * port. The implementation is intentionally simple and adapter-agnostic
 * — when the Doctrine adapter for the Combos port lands in a follow-up,
 * this class can stay as-is and simply talk to the new adapter through
 * the same interface.
 */
final class RepositoryBackedComboGraphResolver implements ComboGraphResolver
{
    public function __construct(
        private readonly Combos $combos,
        private readonly InventoryItems $inventoryItems,
    ) {
    }

    public function isCombo(InventoryItemId $itemId): bool
    {
        try {
            $item = $this->inventoryItems->byId($itemId);
            $this->combos->byListingId($item->listingId());
            return true;
        } catch (InventoryItemNotFound | ComboNotFound) {
            return false;
        }
    }

    public function componentsOf(InventoryItemId $itemId): array
    {
        try {
            $item = $this->inventoryItems->byId($itemId);
            $combo = $this->combos->byListingId($item->listingId());
        } catch (InventoryItemNotFound | ComboNotFound) {
            return [];
        }

        return array_map(
            static fn ($component) => $component->componentItemId,
            $combo->components(),
        );
    }
}
