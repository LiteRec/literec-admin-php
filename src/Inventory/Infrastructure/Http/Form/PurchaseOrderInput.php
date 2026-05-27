<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Form;

/**
 * Symfony Form binding target for the LRA-90 Create Purchase Order
 * page (header + dynamic lines collection).
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
final class PurchaseOrderInput
{
    public ?string $vendorId = null;

    public ?string $facilityCode = null;

    /** @var list<PurchaseOrderLineInput> */
    public array $lines = [];
}
