<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

/**
 * Primitive-only command DTO for the MarkPurchaseOrderSent use case.
 *
 * Transitions a Draft PurchaseOrder to Sent and records the operator's
 * stated send time plus an optional estimated arrival. Both timestamps
 * are ISO-8601 strings parsed inside the handler so the DTO stays
 * trivially serializable across the bus.
 */
final readonly class MarkPurchaseOrderSent
{
    public function __construct(
        public string $purchaseOrderId,
        public string $sentAtIso,
        public ?string $estimatedArrivalIso,
    ) {
    }
}
