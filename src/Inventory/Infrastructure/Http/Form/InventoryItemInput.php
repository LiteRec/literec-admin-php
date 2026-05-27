<?php

declare(strict_types=1);

namespace App\Inventory\Infrastructure\Http\Form;

/**
 * Symfony Form binding target for the LRA-86 Create / Edit Inventory
 * Item dialog.
 *
 * Symfony's PropertyAccessor writes back into the `data_class` instance
 * via reflection on writable properties; that prevents the project's
 * "immutability by default" rule from applying here. The companion
 * application-layer DTOs ({@see \App\Inventory\Application\Command\RegisterInventoryItem}
 * and {@see \App\Inventory\Application\Command\UpdateInventoryItemSettings})
 * stay `final readonly`. This Infrastructure-only adapter exists purely
 * to receive form input and is then transposed into the appropriate
 * command DTO inside the controller.
 *
 * @internal Belongs to the Inventory HTTP boundary; never referenced
 *           from Domain or Application code.
 */
final class InventoryItemInput
{
    public ?string $name = null;

    public ?string $code = null;

    public ?string $kind = null;

    public ?string $vendorId = null;

    public ?string $posColorHex = '#FFFFFF';

    public ?string $ledgerAccount = null;

    public bool $taxApply = false;

    public bool $taxIncludedInFee = false;

    public int $feeAmountCents = 0;

    public bool $trackInventory = true;

    public bool $rentable = false;

    public int $reorderThresholdUnits = 0;
}
