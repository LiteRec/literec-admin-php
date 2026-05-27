<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

/**
 * Primitive-only command DTO for the LRA-86 Edit Inventory Item
 * dialog. Carries every Inventory-side editable field on the item
 * (POS color, primary vendor, tracking/rentable flags, reorder
 * threshold). The handler diffs each field against the loaded
 * aggregate and calls the matching aggregate updater only when the
 * value actually changed, so the resulting domain-event stream
 * mirrors operator intent.
 *
 * Catalog-side fields (name, fees, ledger, tax) are NOT here — the
 * controller dispatches the matching Catalog update commands
 * separately so each context's invariants run independently. The
 * stock-batch collection is never touched by this command; the
 * stock-preservation contract is enforced at the controller level
 * (no archive/delete on edit) and asserted by the functional test.
 */
final readonly class UpdateInventoryItemSettings
{
    public function __construct(
        public string $itemId,
        public string $posColorHex,
        public ?string $primaryVendorId,
        public bool $trackInventory,
        public bool $rentable,
        public int $reorderThresholdUnits,
    ) {
    }
}
