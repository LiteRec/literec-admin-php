<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\Report;

/**
 * Primitive criteria DTO for the LRA-91 Current Stock report card.
 *
 * `kindFilter` is null/empty for "all kinds", or one of the
 * {@see \App\Catalog\Domain\ListingKind} string values to narrow to a
 * single listing kind. `groupId` filters items by membership in the
 * named inventory item group; `facilityCode` scopes the on-hand sum to
 * stock batches at the named facility.
 */
final readonly class CurrentStockReport
{
    public function __construct(
        public ?string $facilityCode = null,
        public ?string $groupId = null,
        public ?string $kindFilter = null,
    ) {
    }
}
