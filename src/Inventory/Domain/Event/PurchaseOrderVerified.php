<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use DateTimeImmutable;

final readonly class PurchaseOrderVerified
{
    public function __construct(
        public PurchaseOrderId $purchaseOrderId,
        public string $verifiedByUserId,
        public DateTimeImmutable $verifiedAt,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
