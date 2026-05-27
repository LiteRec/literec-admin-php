<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Form;

/**
 * Symfony Form binding target for a single line row inside the LRA-90
 * Create Purchase Order form.
 *
 * Form binding requires reflection-writable properties, so this class
 * is not `readonly` — see the rationale documented on
 * {@see InventoryItemInput}. The companion command DTO
 * ({@see \App\Inventory\Application\Command\CreatePurchaseOrder})
 * remains `final readonly`.
 *
 * @internal Belongs to the Inventory HTTP boundary; never referenced
 *           from Domain or Application code.
 */
final class PurchaseOrderLineInput
{
    public ?string $itemId = null;

    public ?int $orderedQuantityUnits = 1;

    public ?int $costPerUnitCents = 0;
}
