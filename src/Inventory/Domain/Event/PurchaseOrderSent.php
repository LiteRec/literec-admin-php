<?php

declare(strict_types=1);

namespace App\Inventory\Domain\Event;

use App\Inventory\Domain\ValueObject\PurchaseOrderId;
use DateTimeImmutable;

final readonly class PurchaseOrderSent
{
    public function __construct(
        public PurchaseOrderId $purchaseOrderId,
        public DateTimeImmutable $sentAt,
        public ?DateTimeImmutable $estimatedArrival,
        public DateTimeImmutable $occurredAt,
    ) {
    }
}
