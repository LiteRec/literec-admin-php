<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Form;

/**
 * Symfony Form binding target for a single component row inside the
 * LRA-89 Combo modal. Each row carries the component InventoryItem id
 * and its quantity-per-combo (units of that item decremented when one
 * combo is sold).
 *
 * Form binding requires reflection-writable properties, so this class
 * is not `readonly` — see the rationale documented on
 * {@see InventoryItemInput}. The companion command DTO
 * ({@see \App\Inventory\Application\Command\DefineCombo}) remains
 * `final readonly`.
 *
 * @internal Belongs to the Inventory HTTP boundary; never referenced
 *           from Domain or Application code.
 */
final class ComboComponentInput
{
    public ?string $componentItemId = null;

    public ?int $quantityPerCombo = 1;
}
