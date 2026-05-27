<?php

declare(strict_types=1);

namespace App\Inventory\Domain;

use App\Inventory\Domain\Exception\InvalidComboComponent;
use App\Inventory\Domain\ValueObject\ComboComponent;
use App\Inventory\Domain\ValueObject\InventoryItemId;
use App\Inventory\Domain\ValueObject\Quantity;

/**
 * Child entity owned by {@see Combo} (LRA-95). Persistence shape only —
 * the public Combo surface still exposes {@see ComboComponent} value
 * objects for events and external consumers. The aggregate stores
 * these entities internally so Doctrine can map the
 * inventory_combo_components child table with a composite key
 * (combo_id, item_id).
 *
 * Identity: (combo_id, item_id). No surrogate id — combos are small,
 * the natural composite is unambiguous, and forComponent() reads
 * exclusively by item_id index.
 *
 * Although the constructor is `public` (PHP has no package-private
 * modifier), callers in Application or Infrastructure layers must
 * drive combo-component lifecycle through {@see Combo} methods.
 */
final class ComboComponentEntity
{
    private Combo $combo;
    private InventoryItemId $componentItemId;
    private Quantity $quantityPerCombo;

    public function __construct(
        Combo $combo,
        InventoryItemId $componentItemId,
        Quantity $quantityPerCombo,
    ) {
        if ($quantityPerCombo->isZero()) {
            throw InvalidComboComponent::zeroQuantity();
        }

        $this->combo = $combo;
        $this->componentItemId = $componentItemId;
        $this->quantityPerCombo = $quantityPerCombo;
    }

    public function combo(): Combo
    {
        return $this->combo;
    }

    public function componentItemId(): InventoryItemId
    {
        return $this->componentItemId;
    }

    public function quantityPerCombo(): Quantity
    {
        return $this->quantityPerCombo;
    }

    /**
     * In-place quantity mutation used by {@see Combo::replaceComponents()}
     * when a component for the same item already exists at the
     * composite-key (combo_id, item_id) — recreating the entity would
     * trigger a Doctrine identity collision in the UnitOfWork because
     * the old instance still claims the same hash until flush.
     */
    public function changeQuantityTo(Quantity $quantityPerCombo): void
    {
        if ($quantityPerCombo->isZero()) {
            throw InvalidComboComponent::zeroQuantity();
        }
        $this->quantityPerCombo = $quantityPerCombo;
    }

    /**
     * Project this entity back to its value-object shape for events,
     * tests, and the public Combo::components() surface.
     */
    public function toValueObject(): ComboComponent
    {
        return new ComboComponent($this->componentItemId, $this->quantityPerCombo);
    }
}
