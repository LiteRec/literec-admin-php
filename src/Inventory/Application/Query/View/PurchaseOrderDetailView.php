<?php

declare(strict_types=1);

namespace App\Inventory\Application\Query\View;

/**
 * Read-side projection of a {@see \App\Inventory\Domain\PurchaseOrder}
 * aggregate for the LRA-90 detail page.
 *
 * `allowedTransitions` is derived in the handler from the aggregate's
 * status; the controller short-circuits any lifecycle POST whose
 * action is not in the list (returning 409).
 *
 * `isLineEditable` is true only while the PO is still a draft (lines
 * may be added/edited before the PO is sent).
 *
 * Timestamps are exposed as ISO-8601 strings so the DTO is trivially
 * serialisable across the Messenger bus and Twig templates can format
 * them with the `date()` filter without first having to construct a
 * DateTime instance.
 */
final readonly class PurchaseOrderDetailView
{
    /**
     * @param list<PurchaseOrderLineDetailView> $lines
     * @param list<string>                      $allowedTransitions
     */
    public function __construct(
        public string $purchaseOrderId,
        public string $vendorId,
        public string $facilityCode,
        public string $status,
        public ?string $sentAtIso,
        public ?string $estimatedArrivalIso,
        public ?string $verifiedAtIso,
        public ?string $verifiedByUserId,
        public string $createdAtIso,
        public string $updatedAtIso,
        public array $lines,
        public array $allowedTransitions,
        public bool $isLineEditable,
    ) {
    }
}
