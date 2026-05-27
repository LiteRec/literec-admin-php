<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

/**
 * Primitive-only cross-bus command DTO (LRA-98). Creates a Catalog
 * Listing and the matching Inventory InventoryItem atomically — both
 * commits or both roll back under the command.bus
 * doctrine_transaction middleware. The LRA-86 Create Item dialog
 * dispatches this single command instead of orchestrating two
 * separate bus calls.
 *
 * Fields mirror the LRA-86 form payload one-to-one and stay
 * primitive so the DTO is trivially serializable across the
 * Messenger transport boundary.
 *
 * @phpstan-type FeeInput array{amountCents: int, currency: string, label: string}
 */
final readonly class RegisterInventoryItem
{
    /**
     * @param list<FeeInput> $fees
     */
    public function __construct(
        public string $code,
        public string $name,
        public string $kind,
        public array $fees,
        public bool $taxApply,
        public bool $taxIncludedInFee,
        public string $ledgerAccount,
        public ?string $primaryVendorId,
        public string $posColorHex,
        public bool $trackInventory,
        public bool $rentable,
        public int $reorderThresholdUnits,
    ) {
    }
}
