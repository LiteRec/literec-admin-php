<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

/**
 * Primitive-only command DTO for the CreatePurchaseOrder use case.
 *
 * Each entry in $lines carries the line's item id, ordered quantity, and
 * cost-per-unit. Line identities are generated inside the handler so
 * the {@see App\Inventory\Domain\Event\PurchaseOrderDrafted} event
 * payload carries stable references before persistence.
 *
 * @phpstan-type LineInput array{itemId: string, orderedQuantityUnits: int, costPerUnitCents: int}
 */
final readonly class CreatePurchaseOrder
{
    /**
     * @param list<LineInput> $lines
     */
    public function __construct(
        public string $vendorId,
        public string $facilityCode,
        public array $lines,
    ) {
    }
}
