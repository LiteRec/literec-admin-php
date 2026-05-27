<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query;

/**
 * Primitive query DTO for the LRA-90 Purchase Order detail page.
 */
final readonly class GetPurchaseOrderDetail
{
    public function __construct(
        public string $purchaseOrderId,
    ) {
    }
}
