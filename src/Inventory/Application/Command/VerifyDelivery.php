<?php

declare(strict_types=1);

namespace App\Inventory\Application\Command;

/**
 * Primitive-only command DTO for the VerifyDelivery use case.
 *
 * Transitions a FullyReceived PurchaseOrder to Verified, recording
 * which user verified the delivery and when. The handler requires
 * every line to be fully received before this transition succeeds.
 */
final readonly class VerifyDelivery
{
    public function __construct(
        public string $purchaseOrderId,
        public string $verifiedByUserId,
        public string $verifiedAtIso,
    ) {
    }
}
