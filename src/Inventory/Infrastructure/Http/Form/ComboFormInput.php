<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Form;

/**
 * Symfony Form binding target for the LRA-89 Create / Edit Combo
 * dialog.
 *
 * Form binding requires reflection-writable properties, so this class
 * is not `readonly` — see the rationale documented on
 * {@see InventoryItemInput}. The companion command DTOs
 * ({@see \App\Inventory\Application\Command\DefineCombo} and
 * {@see \App\Inventory\Application\Command\UpdateComboComponents}) stay
 * `final readonly`.
 *
 * @internal Belongs to the Inventory HTTP boundary; never referenced
 *           from Domain or Application code.
 */
final class ComboFormInput
{
    public ?string $parentListingId = null;

    /** @var list<ComboComponentInput> */
    public array $components = [];
}
