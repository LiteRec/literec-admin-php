<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\ValueObject\FacilityCode;
use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use App\Inventory\Domain\ValueObject\PurchaseOrderLineDraft;
use App\Inventory\Domain\ValueObject\VendorId;
use DateTimeImmutable;

final readonly class PurchaseOrderDrafted
{
    /**
     * @param list<PurchaseOrderLineDraft> $lines
     */
    public function __construct(
        public PurchaseOrderId $purchaseOrderId,
        public VendorId $vendorId,
        public FacilityCode $facilityCode,
        public array $lines,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
