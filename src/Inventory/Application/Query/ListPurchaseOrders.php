<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query;

/**
 * Primitive criteria DTO for the LRA-90 Purchase Orders list page.
 *
 * Null filter fields mean "no filter applied" on that dimension.
 * `status`, when set, must be one of the {@see \App\Inventory\Domain\PurchaseOrderStatus}
 * enum values; the handler enforces that.
 *
 * Date filters are intentionally out of scope for this iteration —
 * adding them requires extending the {@see \App\Inventory\Domain\PurchaseOrders}
 * port. They land in a follow-up if/when the read path demands them.
 */
final readonly class ListPurchaseOrders
{
    public function __construct(
        public ?string $vendorId = null,
        public ?string $status = null,
        public ?string $facilityCode = null,
        public int $pageNumber = 1,
        public int $pageSize = 50,
    ) {
    }
}
