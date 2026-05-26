<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

/**
 * Primitive-only command DTO for the DefineCombo use case.
 *
 * Each entry in $components carries the component InventoryItem id and
 * its quantity-per-combo (units of that item decremented when one combo
 * is sold).
 *
 * @phpstan-type ComponentInput array{itemId: string, quantityPerCombo: int}
 */
final readonly class DefineCombo
{
    /**
     * @param list<ComponentInput> $components
     */
    public function __construct(
        public string $listingId,
        public array $components,
    ) {
    }
}
